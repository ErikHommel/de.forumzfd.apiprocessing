<?php

/**
 * Class for ForumZFD Address API processing to CiviCRM
 *
 * @author Erik Hommel <hommel@ee-atwork.nl>
 * @date 3 July 2017
 * @license AGPL-3.0
 */
class CRM_Apiprocessing_Address {
  public function createNewAddress($params) {
    $result = array();
    $addressFields = civicrm_api3('Address', 'getfields', array());
    foreach ($addressFields['values'] as $addressField) {
      if (isset($params[$addressField['name']])) {
        $addressParams[$addressField['name']] = $params[$addressField['name']];
      }
    }
    // location type id is required so use default if not set
    if (!isset($addressParams['location_type_id']) || empty($addressParams['location_type_id'])) {
      $addressParams['location_type_id'] = CRM_Apiprocessing_Config::singleton()->getDefaultLocationTypeId();
    }

    if (!empty($addressParams) && isset($addressParams['contact_id'])) {
      try {
        $newAddress = civicrm_api3('Address', 'create', $addressParams);
        $result = $newAddress['values'];
      }
      catch (CiviCRM_API3_Exception $ex) {
        CRM_Core_Error::debug_log_message('Could not create new address in '.__METHOD__.', error message from API Address create: '.$ex->getMessage());
      }
    }
    return $result;
  }

}