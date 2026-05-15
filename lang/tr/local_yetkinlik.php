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
 * Turkish strings for local_yetkinlik plugin.
 *
 * @package    local_yetkinlik
 * @copyright  2026 Hakan Çiğci {@link https://hakancigci.com.tr}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'Eylem';
$string['ai_failed'] = 'Yapay zeka isteği başarısız oldu.';
$string['ai_not_configured'] = 'Yapay zeka aktif ancak ayarlar eksik.';
$string['ai_prompt_school'] = 'Aşağıdaki yetkinlik yüzdelerine dayanarak okul için bir pedagojik analiz ve gelişim stratejisi yazın:';
$string['ai_prompt_student'] = 'Aşağıdaki yetkinlik yüzdelerine dayanarak öğrenci için kısa bir pedagojik analiz yazın:';
$string['ai_system_prompt'] = 'Siz bir eğitim asistanısınız. Öğrenciler veya okullar için motivasyonel ve pedagojik geri bildirimler sağlayın.';
$string['allcompetencies'] = 'Tüm Yetkinlikler';
$string['alltime'] = 'Tüm zamanlar';
$string['allusers'] = 'Tüm Öğrenciler';
$string['analysisfor'] = 'Kazanım Analizim: {$a}';
$string['apikey'] = 'API Anahtarı';
$string['apikey_desc'] = 'OpenAI veya Azure OpenAI API anahtarınızı girin. <a href="https://platform.openai.com/account/api-keys" target="_blank">OpenAI anahtarı için tıklayın</a>.';
$string['bluelegend'] = 'Mavi: Büyük Oranda Kazanıldı (%60–79)';
$string['btn_process_now'] = 'Başarı Oranlarını Arka Planda İşle';
$string['classavg'] = 'Sınıf Ortalaması';
$string['classinfo'] = 'Sınıf: {$a}';
$string['classreport'] = 'Sınıf Kazanım Raporu';
$string['colorlegend'] = 'Renk Anahtarı:';
$string['comment'] = 'Yorum';
$string['comment_blue'] = 'Büyük oranda öğrenilen konular: {$a}';
$string['comment_green'] = 'Tam öğrenilen konular: {$a}';
$string['comment_orange'] = 'Kısmen öğrenilen konular: {$a}';
$string['comment_red'] = 'Henüz kazanılamayan konular: {$a}';
$string['compareinfo'] = 'Bu raporda kendi başarınızı, kursun geneli ve sınıfınızla kıyaslayabilirsiniz.';
$string['competency'] = 'Yetkinlik / Kazanım';
$string['competencycode'] = 'Yetkinlik Kodu';
$string['competencyname'] = 'Kazanım / Yetkinlik';
$string['correct'] = 'Doğru';
$string['correctcount'] = 'Doğru Sayısı';
$string['courseavg'] = 'Kurs Ortalaması';
$string['creation_date'] = 'Oluşturulma Tarihi';
$string['enable_ai'] = 'Yapay Zeka Entegrasyonunu Aktif Et';
$string['enable_ai_desc'] = 'Yapay zeka tabanlı pedagojik yorumları aktif eder. API anahtarı ve model seçimi aşağıdan yapılmalıdır.';
$string['error_no_enrolment'] = 'Bu kursa kayıtlı olmadığınız için raporu görüntüleyemezsiniz.';
$string['evidence'] = 'Kanıt';
$string['evidence_description'] = 'Yetkinlik {$a->competency} için başarı: %{$a->rate}';
$string['evidence_note'] = 'Yetkinlik {$a->competency} için başarı: %{$a->rate}';
$string['filter'] = 'Filtrele';
$string['filterlabel'] = 'Filtrele';
$string['generalcomment'] = 'Genel Yorum';
$string['greenlegend'] = 'Yeşil: Tam Kazanıldı (%80+)';
$string['groupcompetency'] = 'Grup Yetkinlik Analizi';
$string['groupquizcompetency'] = 'Grup Sınav Yetkinlik Analizi';
$string['last30days'] = 'Son 30 gün';
$string['last90days'] = 'Son 90 gün';
$string['maxrows'] = 'Maksimum satır';
$string['maxrows_desc'] = 'Tablolarda görüntülenecek maksimum satır sayısı.';
$string['model'] = 'Model';
$string['model_desc'] = 'Kullanılacak model adını girin (Örn: gpt-4).';
$string['myavg'] = 'Benim Başarım';
$string['mycompetencies'] = 'Kazanım Analizlerim';
$string['mycompetencyexams'] = 'Yetkinlik Bazlı Sınavlarım';
$string['mycompetencystate'] = 'Yetkinlik Durumu';
$string['myexamanalysis'] = 'Sınav Kazanım Analizim';
$string['myreportcard'] = 'Karnem';
$string['nocompetencies'] = 'Yetkinlik yok.';
$string['nocompetencyexamdata'] = 'Bu yetkinlik için sınav verisi bulunamadı.';
$string['nodatafound'] = 'Bu kursta henüz analiz edilecek tamamlanmış sınav verisi bulunamadı.';
$string['nodatastudentcompetency'] = 'Bu öğrenci için bu yetkinlikte sınav verisi bulunamadı.';
$string['noexamdata'] = 'Bu sınav için yetkinlik verisi bulunamadı.';
$string['orangelegend'] = 'Turuncu: Kısmen Kazanıldı (%40–59)';
$string['pdfmystudent'] = '📄 PDF Karnemi Görüntüle';
$string['pdfreport'] = '📄 PDF Raporu';
$string['pluginname'] = 'Yetkinlik Analiz Sistemi';
$string['privacy:metadata'] = 'Yetkinlik eklentisi herhangi bir kişisel veri depolamaz.';
$string['privacy:metadata:openai:answertext'] = 'The student\'s response is sent to be evaluated by the AI model.';
$string['privacy:metadata:openai:externalpurpose'] = 'The plugin sends question texts and user responses to the OpenAI API to provide AI-generated feedback and competency analysis.';
$string['privacy:metadata:openai:questiontext'] = 'The text of the question is sent to provide context for the AI analysis.';
$string['process_queued'] = 'Başarı oranı hesaplama işlemi kuyruğa eklendi. Arka planda tamamlanacak.';
$string['process_success_desc'] = 'Bu işlem öğrencilerin sınav sorularındaki başarı yüzdelerini hesaplayıp kanıt olarak ekler.';
$string['process_success_heading'] = 'Yüzdelik Başarıları Kanıtlara Aktar';
$string['process_success_title'] = 'Başarıları Arka Planda İşle';
$string['question'] = 'Soru';
$string['questioncount'] = 'Soru Sayısı';
$string['questionlinks'] = 'İlgili Soru Detayları';
$string['questionname'] = 'Soru Adı';
$string['quiz'] = 'Sınav';
$string['recordupdated'] = 'Kayıt başarıyla güncellendi';
$string['redlegend'] = 'Kırmızı: Kazanılmadı (%0–39)';
$string['report_heading'] = 'Yetkinlik Analizi Detaylı Raporu';
$string['report_title'] = 'Detaylı Yetkinlik Raporu';
$string['savechanges'] = 'Değişiklikleri Kaydet';
$string['schoolpdf'] = 'Okul PDF Raporu';
$string['schoolpdfreport'] = 'Okul Genel Başarı Raporu';
$string['schoolreport'] = 'Okul Genel Raporu';
$string['searchcompetency'] = 'Kazanım ara';
$string['searchquiz'] = 'Sınav ara';
$string['searchuserorprept'] = 'Öğrenci veya rapor ara';
$string['selectcompetency'] = 'Yetkinlik seçiniz';
$string['selectgroup'] = 'Grup seçiniz';
$string['selectquiz'] = 'Sınav seçiniz';
$string['selectstudent'] = 'Öğrenci seçiniz';
$string['selectuser'] = 'Öğrenci seçiniz';
$string['show'] = 'Göster';
$string['structured_blue'] = '{$a->shortname}: Başarı oranı %{$a->rate}. Büyük oranda öğrenildi. Öneri: Eksik kalan noktaları gözden geçir.';
$string['structured_green'] = '{$a->shortname}: Başarı oranı %{$a->rate}. Tam başarı sağlandı. Öneri: İleri düzey etkinliklere geçebilirsin.';
$string['structured_orange'] = '{$a->shortname}: Başarı oranı %{$a->rate}. Kısmen öğrenildi. Öneri: Daha fazla örnek soru çözerek pekiştir.';
$string['structured_red'] = '{$a->shortname}: Başarı oranı %{$a->rate}. Henüz yeterli gelişim sağlanamadı. Öneri: Konuyu tekrar et ve ek kaynaklardan yararlan.';
$string['student'] = 'Öğrenci';
$string['studentanalysis'] = 'Öğrenci Yetkinlik Analizi';
$string['studentavg'] = 'Öğrenci Ortalaması';
$string['studentclass'] = 'Yetkinlik Durumu';
$string['studentcompetencydetail'] = 'Öğrenci Yetkinlik Detayı';
$string['studentcompetencyexams'] = 'Yetkinlik Bazlı Sınav Analizim';
$string['studentexam'] = 'Sınav Kazanım Analizim';
$string['studentexamanalysis'] = 'Öğrenci Sınav Analizi';
$string['studentpdfreport'] = 'Yetkinlik Gelişim Raporu';
$string['studentreport'] = 'Yetkinlik Karnem';
$string['success'] = 'Başarı';
$string['success_threshold'] = 'Başarı eşiği';
$string['success_threshold_desc'] = 'Renk kodlaması için varsayılan başarı yüzdesi.';
$string['successpercent'] = 'Başarı Yüzdesi';
$string['successrate'] = 'Başarı Oranı (%)';
$string['summaryreport'] = 'Yetkinlik Başarı Özeti';
$string['teacherstudentcompetency'] = 'Öğrenci Yetkinlik Analizi';
$string['timeline'] = 'Zaman Çizelgesi';
$string['timelineheading'] = 'Zaman İçinde Yetkinlik Gelişimi';
$string['total'] = 'TOPLAM';
$string['user'] = 'Öğrenci';
$string['viewattempt'] = 'İnceleme';
$string['visual_report'] = 'Görsel rapor';
$string['yetkinlik:manage'] = 'Soru-yetkinlik eşleştirmelerini yönet';
$string['yetkinlik:viewownreport'] = 'Kendi yetkinlik analiz raporunu görüntüle';
$string['yetkinlik:viewreports'] = 'Tüm öğrenci yetkinlik raporlarını görüntüle';
