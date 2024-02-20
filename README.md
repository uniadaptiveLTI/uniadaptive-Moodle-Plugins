# UNIAdaptiveLTI-Moodle-Plugin

UNIAdaptiveLTI-Moodle-Plugin is a plugin that allows adding webservice for the correct functioning of the UNIAdaptive tool.

## Features

- Gets the badges of a course
- Gets the resources with grades
- Gets the ids of the grades
- Gets the grades of the course
- Exports the map to Moodle
- Lists modules and allows excluding by types
- Gets the list of ids of modules by sections

- Add role.
  The plugin creates a role ready to be added to the user for consumption of web services. This is configured to be added from systems, this is the list of permissions it must have :
  - gradereport/user:view
  - moodle/course:managegroups
  - moodle/course:view
  - moodle/course:viewhiddencourses
  - moodle/course:viewhiddensections
  - moodle/course:viewparticipants
  - moodle/user:viewdetails
  - moodle/user:viewhiddendetails
  - webservice/rest:use
  - mod/assign:view
  - mod/data:view
  - mod/feedback
  - mod/glossary
  - mod/h5pactivity
  - mod/lti:views
  - mod/quiz:view
  - mod/workshop:view
  - moodle/course:ignoreavailabilityrestrictions
  - moodle/course:viewhiddenactivities
  - moodle/site:accessallgroups

## Requirements

- Moodle 3.9.X or higher

## Installation

- Download the ZIP file of the plugin.
- Access your Moodle site as an administrator and go to Site administration > Extensions > Install plugins > Install plugin from a ZIP file to complete the installation of the plugin.

## Configuration

- To configure the options of the plugin, go to Site administration > Extensions > Local extensions > Manage local extensions.
- To generate an access key to the webservice, go to Site administration > Extensions > Web services > External keys and create a new key for the user you want.
- To see the list of available functions of the webservice, go to Site administration > Extensions > Web services > Documentation.
- To add the webservice to the lti, you must go to Site administration > Server > Web services > External services > Functions > Add functions.
  The web services that you must add are:
  - local_uniadaptive_get_course_badges
  - local_uniadaptive_get_course_grade_with_califications
  - local_uniadaptive_get_course_item_id_for_grade_id
  - local_uniadaptive_get_course_modules
  - local_uniadaptive_get_course_modules_by_type
  - local_uniadaptive_get_coursegrades
  - local_uniadaptive_get_id_grade
  - local_uniadaptive_get_modules_list_by_sections_course
  - local_uniadaptive_set_modules_list_by_sections
  - local_uniadaptive_update_course_badges_criteria
  - local_uniadaptive_get_assignable_roles
  - local_uniadaptive_get_course_competencies
  - local_uniadaptive_update_course
  - local_uniadaptive_get_module_data
  - local_uniadaptive_get_course_grade_id
  - local_uniadaptive_check_user'
  - local_uniadaptive_check_token'

## License

UNIAdaptiveLTI-Moodle-Plugin is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.html).

![EN-Funded by the EU-BLACK Outline](https://github.com/uniadaptiveLTI/uniadaptive-Moodle-Plugins/assets/91719773/eb8bbed6-29ba-47ed-96b9-86cac9437aea)
