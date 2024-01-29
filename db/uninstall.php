<?php
function xmldb_local_uniadaptive_uninstall()
{
    global $DB;

    // Define the name of the service.
    $servicename = 'UNIAdaptive';

    // Get the service record.
    $service = $DB->get_record('external_services', array('name' => $servicename));

    if ($service) {
        // Delete the functions associated with the service.
        $DB->delete_records('external_services_functions', array('externalserviceid' => $service->id));

        // Delete the service.
        $DB->delete_records('external_services', array('id' => $service->id));
    }
}
