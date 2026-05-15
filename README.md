# local_competency_report

A Moodle **Competency Analysis and Reporting** plugin. This plugin analyzes and reports on user competencies based on the questionâ€‘competency links created by `qbank_competency`.

## Features
- Generate analysis based on competencyâ€‘linked questions
- Track user competency progress
- Provide colorâ€‘coded and motivational feedback
- Export PDF reports
- Multiâ€‘language support
- Works in integration with `qbank_competency`

## Installation
1. Copy this plugin into `moodle/local/competency_report`.
2. In Moodle, go to **Site administration â†’ Plugins â†’ Install plugins**.
3. Complete the installation wizard.
4. Purge caches.

## Usage
- First, assign competencies to questions using `qbank_competency`.
- This plugin then produces analysis and reports based on those assignments.
- Feedback is automatically generated with color codes and motivational messages.

## Development
- Source JS files are located in `amd/src`.
- After changes, run `grunt amd` to regenerate `amd/build` files.
- Language files are located in `lang/en` and `lang/tr`.

## License
This plugin is distributed under the GNU GPL v3 license.