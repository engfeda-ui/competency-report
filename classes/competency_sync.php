<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Centralised competency sync engine.
 *
 * ALL writes to Moodle's native competency tables go through this single
 * class so that the three sync entry points (quiz observer, nightly cron,
 * manual admin trigger) stay consistent and never duplicate evidence.
 *
 * What it guarantees
 * ------------------
 *  - Uses the SAME weighted calculator as the report pages
 *    (local_comp_report_ext\competency_calculator), so Moodle's native UI
 *    shows identical competency rates to the plugin reports.
 *  - Always sets competency_usercomp.proficiency (1/0), so the native
 *    Competency Breakdown marks the student "Proficient" automatically.
 *  - Enforces strictly ONE competency_evidence row per
 *    (user, competency) — old duplicates are purged on every run.
 *  - Purges redundant competency_userevidence / competency_userevidencecomp
 *    rows so the native modal shows a single evidence card.
 *  - Never hardcodes admin IDs; the acting user id is always supplied.
 *
 * @package    local_comp_report_ext
 * @copyright  2026 Mahmoud Salem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_comp_report_ext;

/**
 * Centralised competency synchronisation helper.
 *
 * @package local_comp_report_ext
 */
class competency_sync {
    /**
     * Resolve the acting admin / grader user id.
     *
     * Falls back gracefully: caller-supplied id → site primary admin → guest-safe 0.
     *
     * @param int $preferredid Preferred user id (0 to auto-detect).
     * @return int
     */
    public static function resolve_grader_id(int $preferredid = 0): int {
        global $CFG;
        if ($preferredid > 0) {
            return $preferredid;
        }
        try {
            require_once($CFG->libdir . '/moodlelib.php');
            $admin = get_admin();
            if ($admin && $admin->id > 0) {
                return (int)$admin->id;
            }
        } catch (\Throwable $e) {
            unset($e);
        }
        return 0;
    }

    /**
     * Sync competency proficiency + evidence for a single student in a course.
     *
     * Returns an associative array of competency shortname => percent (0-100)
     * for ALL competencies the student has attempted, so callers can use it
     * for at-risk notifications.
     *
     * @param int $userid   Student user id.
     * @param int $courseid Course id.
     * @param int $graderid Acting user id (0 to auto-detect site admin).
     * @return array [shortname => percent]
     */
    public static function sync_user_competency(int $userid, int $courseid, int $graderid = 0): array {
        global $DB;

        if ($userid <= 0 || $courseid <= 0) {
            return [];
        }

        $graderid = self::resolve_grader_id($graderid);
        $threshold = (int)(get_config('local_comp_report_ext', 'success_threshold') ?: 60);
        $contextid = (int)\context_course::instance($courseid)->id;
        $now = time();

        // 1. Compute scores via the SAME weighted engine used by the reports.
        // This respects configured assessment weights (quizzes + practical
        // exams) and falls back to a plain question average when no
        // weights are configured. Only finished attempts are considered.
        $calculator = new competency_calculator($courseid);
        $scores = $calculator->get_student_scores($userid);

        if (empty($scores)) {
            return [];
        }

        $ratebyshort = [];

        foreach ($scores as $competencyid => $data) {
            $percent = (float)$data['percent'];
            $isproficient = ($percent >= $threshold) ? 1 : 0;
            $ratestr = number_format($percent, 1);

            $comp = isset($data['competency']) ? $data['competency'] : null;
            $shortname = $comp ? $comp->shortname : ('#' . $competencyid);
            $ratebyshort[$shortname] = $percent;

            $a = new \stdClass();
            $a->competency = $shortname;
            $a->rate = $ratestr;

            // 2. upsert competency_usercomp — ALWAYS set proficiency.
            $uc = $DB->get_record('competency_usercomp', [
                'userid'       => $userid,
                'competencyid' => $competencyid,
            ]);
            if (!$uc) {
                $uc = new \stdClass();
                $uc->userid       = $userid;
                $uc->competencyid = $competencyid;
                $uc->status       = 0; // STATUS_IDLE: graded and shown by Moodle.
                $uc->proficiency  = $isproficient;
                $uc->timecreated  = $now;
                $uc->timemodified = $now;
                $uc->usermodified = $graderid;
                $uc->id = $DB->insert_record('competency_usercomp', $uc);
            } else {
                $uc->status       = 0;
                $uc->proficiency  = $isproficient;
                $uc->timemodified = $now;
                $uc->usermodified = $graderid;
                $DB->update_record('competency_usercomp', $uc);
            }

            // 3. upsert competency_usercompcourse (course-scoped proficiency).
            $ucc = $DB->get_record('competency_usercompcourse', [
                'userid'       => $userid,
                'courseid'     => $courseid,
                'competencyid' => $competencyid,
            ]);
            if (!$ucc) {
                $ucc = new \stdClass();
                $ucc->userid       = $userid;
                $ucc->courseid     = $courseid;
                $ucc->competencyid = $competencyid;
                $ucc->proficiency  = $isproficient;
                $ucc->timecreated  = $now;
                $ucc->timemodified = $now;
                $ucc->usermodified = $graderid;
                $DB->insert_record('competency_usercompcourse', $ucc);
            } else {
                $ucc->proficiency  = $isproficient;
                $ucc->timemodified = $now;
                $ucc->usermodified = $graderid;
                $DB->update_record('competency_usercompcourse', $ucc);
            }

            // 4. Purge redundant competency_userevidence + link rows so the
            // native evidence modal shows at most ONE card per competency.
            $userlinks = $DB->get_records_sql(
                "SELECT l.id AS linkid, e.id AS evidenceid
                   FROM {competency_userevidencecomp} l
                   JOIN {competency_userevidence} e ON e.id = l.userevidenceid
                  WHERE e.userid = :userid AND l.competencyid = :compid",
                ['userid' => $userid, 'compid' => $competencyid]
            );
            if (!empty($userlinks)) {
                foreach ($userlinks as $link) {
                    $DB->delete_records('competency_userevidencecomp', ['id' => $link->linkid]);
                    $DB->delete_records('competency_userevidence', ['id' => $link->evidenceid]);
                }
            }

            // 5. Enforce strictly ONE competency_evidence row per usercompetency.
            $existing = $DB->get_records_sql(
                "SELECT id
                   FROM {competency_evidence}
                  WHERE usercompetencyid = :ucid
               ORDER BY id DESC",
                ['ucid' => $uc->id]
            );

            $note = get_string('evidence_note', 'local_comp_report_ext', $a);

            if (!empty($existing)) {
                $newest = array_shift($existing);
                // Purge any older duplicate evidence logs.
                if (!empty($existing)) {
                    $DB->delete_records_list('competency_evidence', 'id', array_keys($existing));
                }
                $newest->grade          = (int)round($percent);
                $newest->note           = $note;
                $newest->contextid      = $contextid;
                $newest->desccomponent  = 'local_comp_report_ext';
                $newest->descidentifier = 'evidence';
                $newest->desca          = null;
                $newest->timemodified   = $now;
                $newest->usermodified   = $graderid;
                $DB->update_record('competency_evidence', $newest);
            } else {
                $cevidence = new \stdClass();
                $cevidence->usercompetencyid = $uc->id;
                $cevidence->contextid        = $contextid;
                $cevidence->action           = 1;
                $cevidence->actionuserid     = $graderid;
                $cevidence->descidentifier   = 'evidence';
                $cevidence->desccomponent    = 'local_comp_report_ext';
                $cevidence->desca            = null;
                $cevidence->url              = '';
                $cevidence->grade            = (int)round($percent);
                $cevidence->note             = $note;
                $cevidence->timecreated      = $now;
                $cevidence->timemodified     = $now;
                $cevidence->usermodified     = $graderid;
                $DB->insert_record('competency_evidence', $cevidence);
            }
        }

        return $ratebyshort;
    }

    /**
     * Sync all enrolled students in a course (used by cron + manual trigger).
     *
     * @param int $courseid Course id.
     * @param int $graderid Acting user id (0 to auto-detect site admin).
     * @return int Number of students processed.
     */
    public static function sync_course(int $courseid, int $graderid = 0): int {
        global $DB;

        if ($courseid <= 0) {
            return 0;
        }

        // Only process courses that actually have competency mappings.
        $mapped = $DB->record_exists('qbank_comp_ext_qmap', ['courseid' => $courseid]);
        if (!$mapped) {
            return 0;
        }

        $graderid = self::resolve_grader_id($graderid);
        $context = \context_course::instance($courseid);

        // Active enrolled students only (not all site users).
        $students = get_enrolled_users($context, 'mod/quiz:attempt', 0, 'u.*', null, 0, 0, true);

        if (empty($students)) {
            return 0;
        }

        $count = 0;
        foreach ($students as $student) {
            try {
                self::sync_user_competency((int)$student->id, $courseid, $graderid);
                $count++;
            } catch (\Throwable $e) {
                // Never let one student break the whole course sync.
                debugging('competency_sync student ' . $student->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                unset($e);
            }
        }

        self::purge_duplicate_evidence();
        return $count;
    }

    /**
     * Purge all duplicate competency_evidence records site-wide.
     * Keeps strictly the single newest evidence record per usercompetency.
     *
     * @return void
     */
    public static function purge_duplicate_evidence(): void {
        global $DB;
        try {
            $dupes = $DB->get_records_sql(
                "SELECT id, usercompetencyid
                   FROM {competency_evidence}
               ORDER BY usercompetencyid ASC, id DESC"
            );
            if (empty($dupes)) {
                return;
            }
            $seen = [];
            $todelete = [];
            foreach ($dupes as $row) {
                if (isset($seen[$row->usercompetencyid])) {
                    $todelete[] = (int)$row->id;
                } else {
                    $seen[$row->usercompetencyid] = true;
                }
            }
            if (!empty($todelete)) {
                $chunks = array_chunk($todelete, 1000);
                foreach ($chunks as $chunk) {
                    $DB->delete_records_list('competency_evidence', 'id', $chunk);
                }
            }
        } catch (\Throwable $e) {
            unset($e);
        }
    }
}
