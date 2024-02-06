<?php



function xmldb_local_uniadaptive_install()
{
    global $CFG, $DB;
    require_once($CFG->dirroot . '/user/lib.php');
    require_once($CFG->libdir . '/externallib.php');

    // Define the service.
    $servicerecord = new stdClass;
    $servicerecord->name = get_string('pluginname', 'local_uniadaptive');
    $servicerecord->requiredcapability = '';
    $servicerecord->restrictedusers = 1;
    $servicerecord->shortname = get_string('pluginshortname', 'local_uniadaptive');
    $servicerecord->enabled = 1;
    $servicerecord->timecreated = time(); // Add this line

    // Check if the service already exists.
    $existing_service = $DB->get_record('external_services', array('name' => $servicerecord->name));

    if ($existing_service) {
        // If the service exists, update it.
        $servicerecord->id = $existing_service->id;
        $DB->update_record('external_services', $servicerecord);
        $serviceid = $existing_service->id;
    } else {
        // If the service does not exist, insert it.
        $serviceid = $DB->insert_record('external_services', $servicerecord);
        if (!$serviceid) {
            throw new Exception('Error to insert the service.');
        }
    }

    // Define the functions that should be included in the service.
    $functions = array(
        'core_competency_list_course_module_competencies',
        'core_course_check_updates',
        'core_course_edit_module',
        'core_course_get_contents',
        'core_course_get_course_module',
        'core_course_get_course_module_by_instance',
        'core_course_get_module',
        'core_course_update_courses',
        'core_enrol_get_enrolled_users',
        'core_enrol_get_enrolled_users_with_capability',
        'core_enrol_get_users_courses',
        'core_group_get_course_groupings',
        'core_group_get_course_groups',
        'core_user_get_course_user_profiles',
        'core_user_get_users_by_field',
        'gradereport_user_get_grade_items',
        'local_uniadaptive_get_course_badges',
        'local_uniadaptive_get_course_grade_with_califications',
        'local_uniadaptive_get_course_item_id_for_grade_id',
        'local_uniadaptive_get_course_modules',
        'local_uniadaptive_get_course_modules_by_type',
        'local_uniadaptive_get_coursegrades',
        'local_uniadaptive_get_id_grade',
        'local_uniadaptive_get_modules_list_by_sections_course',
        'local_uniadaptive_set_modules_list_by_sections',
        'local_uniadaptive_update_course_badges_criteria',
        'local_uniadaptive_get_assignable_roles',
        'local_uniadaptive_get_course_competencies',
        'local_uniadaptive_update_course',
        'local_uniadaptive_get_module_data',
        'local_uniadaptive_get_course_grade_id',
        'local_uniadaptive_check_user',
        'local_uniadaptive_check_token'
    );

    // Add the functions to the service.
    foreach ($functions as $function) {
        // If the service already existed, check if the function already exists in the service.
        $existing_function = $DB->get_record('external_services_functions', array('externalserviceid' => $serviceid, 'functionname' => $function));

        if ($existing_function) {
            // If the function already exists in the service, skip to the next function.
            continue;
        }

        // If the function does not exist in the service or if the service did not exist, insert it.
        $functionrecord = new stdClass;
        $functionrecord->externalserviceid = $serviceid;
        $functionrecord->functionname = $function;
        if (!$DB->insert_record('external_services_functions', $functionrecord)) {
            throw new Exception('Error to insert the function.');
        }
    }

    $shortname = get_string('pluginshortname', 'local_uniadaptive');

    if (!$DB->record_exists('role', array('shortname' => $shortname))) {
        $fullname = get_string('pluginname', 'local_uniadaptive');
        $description = get_string('pluginroledescription', 'local_uniadaptive');

        $roleid = create_role($fullname, $shortname, $description);

        $capabilities = array(
            'gradereport/user:view',
            'moodle/course:managegroups',
            'moodle/course:view',
            'moodle/course:viewhiddencourses',
            'moodle/course:viewhiddensections',
            'moodle/course:viewparticipants',
            'moodle/user:viewdetails',
            'moodle/user:viewhiddendetails',
            'webservice/rest:use',
            'mod/assign:view',
            'mod/data:view',
            'mod/feedback:view',
            'mod/glossary:view',
            'mod/h5pactivity:view',
            'mod/lti:view',
            'mod/quiz:view',
            'mod/workshop:view',
            'moodle/course:ignoreavailabilityrestrictions',
            'moodle/course:viewhiddenactivities',
            'moodle/site:accessallgroups'
        );

        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, context_system::instance()->id);
        }

        $contextlevels = array(CONTEXT_SYSTEM);

        set_role_contextlevels($roleid, $contextlevels);
    }

    return true;
}
