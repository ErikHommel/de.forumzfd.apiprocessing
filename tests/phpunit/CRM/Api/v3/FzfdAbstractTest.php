<?php

class CRM_Api_v3_FzfdAbstractTest extends \PHPUnit_Framework_TestCase {

 /**
   * Emulate a logged in user since certain functions use that.
   * value to store a record in the DB (like activity)
   * CRM-8180
   *
   * @return int
   *   Contact ID of the created user.
   */
  public function createLoggedInUser() {
    $params = array(
      'first_name' => 'Logged In',
      'last_name' => 'User ' . rand(),
      'contact_type' => 'Individual',
    );
		$contact = civicrm_api3('Contact', 'create', $params);
    $contactID = $contact['id'];
    civicrm_api3('UFMatch', 'create', array(
      'contact_id' => $contactID,
      'uf_name' => 'superman',
      'uf_id' => 6,
    ));

    $session = CRM_Core_Session::singleton();
    $session->set('userID', $contactID);
    return $contactID;
  }
	
	/**
   * Check that api returned 'is_error' => 0.
   *
   * @param array $apiResult
   *   Api result.
   * @param string $prefix
   *   Extra test to add to message.
   */
  public function assertAPISuccess($apiResult, $prefix = '') {
    if (!empty($prefix)) {
      $prefix .= ': ';
    }
    $errorMessage = empty($apiResult['error_message']) ? '' : " " . $apiResult['error_message'];

    if (!empty($apiResult['debug_information'])) {
      $errorMessage .= "\n " . print_r($apiResult['debug_information'], TRUE);
    }
    if (!empty($apiResult['trace'])) {
      $errorMessage .= "\n" . print_r($apiResult['trace'], TRUE);
    }
    $this->assertEquals(0, $apiResult['is_error'], $prefix . $errorMessage);
  }

  /**
   * Check that api returned 'is_error' => 1.
   *
   * @param array $apiResult
   *   Api result.
   * @param string $prefix
   *   Extra test to add to message.
   * @param null $expectedError
   */
  public function assertAPIFailure($apiResult, $prefix = '', $expectedError = NULL) {
    if (!empty($prefix)) {
      $prefix .= ': ';
    }
    if ($expectedError && !empty($apiResult['is_error'])) {
      $this->assertEquals($expectedError, $apiResult['error_message'], 'api error message not as expected' . $prefix);
    }
    $this->assertEquals(1, $apiResult['is_error'], "api call should have failed but it succeeded " . $prefix . (print_r($apiResult, TRUE)));
    $this->assertNotEmpty($apiResult['error_message']);
  }
	
	/**
   * wrap api functions.
   * so we can ensure they succeed & throw exceptions without litterering the test with checks
   *
   * @param string $entity
   * @param string $action
   * @param array $params
   * @param mixed $checkAgainst
   *   Optional value to check result against, implemented for getvalue,.
   *   getcount, getsingle. Note that for getvalue the type is checked rather than the value
   *   for getsingle the array is compared against an array passed in - the id is not compared (for
   *   better or worse )
   *
   * @return array|int
   */
  public function callAPISuccess($entity, $action, $params, $checkAgainst = NULL) {
    $params = array_merge(array(
        'version' => $this->_apiversion,
        'debug' => 1,
      ),
      $params
    );
    switch (strtolower($action)) {
      case 'getvalue':
        return $this->callAPISuccessGetValue($entity, $params, $checkAgainst);

      case 'getsingle':
        return $this->callAPISuccessGetSingle($entity, $params, $checkAgainst);

      case 'getcount':
        return $this->callAPISuccessGetCount($entity, $params, $checkAgainst);
    }
    $result = $this->civicrm_api($entity, $action, $params);
    $this->assertAPISuccess($result, "Failure in api call for $entity $action");
    return $result;
  }

  /**
   * This function exists to wrap api getValue function & check the result
   * so we can ensure they succeed & throw exceptions without litterering the test with checks
   * There is a type check in this
   *
   * @param string $entity
   * @param array $params
   * @param string $type
   *   Per http://php.net/manual/en/function.gettype.php possible types.
   *   - boolean
   *   - integer
   *   - double
   *   - string
   *   - array
   *   - object
   *
   * @return array|int
   */
  public function callAPISuccessGetValue($entity, $params, $type = NULL) {
    $params += array(
      'version' => $this->_apiversion,
      'debug' => 1,
    );
    $result = $this->civicrm_api($entity, 'getvalue', $params);
    if ($type) {
      if ($type == 'integer') {
        // api seems to return integers as strings
        $this->assertTrue(is_numeric($result), "expected a numeric value but got " . print_r($result, 1));
      }
      else {
        $this->assertType($type, $result, "returned result should have been of type $type but was ");
      }
    }
    return $result;
  }

  /**
   * This function exists to wrap api getsingle function & check the result
   * so we can ensure they succeed & throw exceptions without litterering the test with checks
   *
   * @param string $entity
   * @param array $params
   * @param array $checkAgainst
   *   Array to compare result against.
   *   - boolean
   *   - integer
   *   - double
   *   - string
   *   - array
   *   - object
   *
   * @throws Exception
   * @return array|int
   */
  public function callAPISuccessGetSingle($entity, $params, $checkAgainst = NULL) {
    $params += array(
      'version' => $this->_apiversion,
    );
    $result = $this->civicrm_api($entity, 'getsingle', $params);
    if (!is_array($result) || !empty($result['is_error']) || isset($result['values'])) {
      throw new Exception('Invalid getsingle result' . print_r($result, TRUE));
    }
    if ($checkAgainst) {
      // @todo - have gone with the fn that unsets id? should we check id?
      $this->checkArrayEquals($result, $checkAgainst);
    }
    return $result;
  }

  /**
   * This function exists to wrap api getValue function & check the result
   * so we can ensure they succeed & throw exceptions without litterering the test with checks
   * There is a type check in this
   * @param string $entity
   * @param array $params
   * @param null $count
   * @throws Exception
   * @return array|int
   */
  public function callAPISuccessGetCount($entity, $params, $count = NULL) {
    $params += array(
      'version' => $this->_apiversion,
      'debug' => 1,
    );
    $result = $this->civicrm_api($entity, 'getcount', $params);
    if (!is_int($result) || !empty($result['is_error']) || isset($result['values'])) {
      throw new Exception('Invalid getcount result : ' . print_r($result, TRUE) . " type :" . gettype($result));
    }
    if (is_int($count)) {
      $this->assertEquals($count, $result, "incorrect count returned from $entity getcount");
    }
    return $result;
  }

  /**
   * This function exists to wrap api functions.
   * so we can ensure they succeed, generate and example & throw exceptions without litterering the test with checks
   *
   * @param string $entity
   * @param string $action
   * @param array $params
   * @param string $function
   *   Pass this in to create a generated example.
   * @param string $file
   *   Pass this in to create a generated example.
   * @param string $description
   * @param string|null $exampleName
   *
   * @return array|int
   */
  public function callAPIAndDocument($entity, $action, $params, $function, $file, $description = "", $exampleName = NULL) {
    $params['version'] = $this->_apiversion;
    $result = $this->callAPISuccess($entity, $action, $params);
    $this->documentMe($entity, $action, $params, $result, $function, $file, $description, $exampleName);
    return $result;
  }

  /**
   * This function exists to wrap api functions.
   * so we can ensure they fail where expected & throw exceptions without litterering the test with checks
   * @param string $entity
   * @param string $action
   * @param array $params
   * @param string $expectedErrorMessage
   *   Error.
   * @param null $extraOutput
   * @return array|int
   */
  public function callAPIFailure($entity, $action, $params, $expectedErrorMessage = NULL, $extraOutput = NULL) {
    if (is_array($params)) {
      $params += array(
        'version' => $this->_apiversion,
      );
    }
    $result = $this->civicrm_api($entity, $action, $params);
    $this->assertAPIFailure($result, "We expected a failure for $entity $action but got a success", $expectedErrorMessage);
    return $result;
  }
	
	/**
   * A stub for the API interface. This can be overriden by subclasses to change how the API is called.
   *
   * @param $entity
   * @param $action
   * @param array $params
   * @return array|int
   */
  public function civicrm_api($entity, $action, $params) {
    return civicrm_api($entity, $action, $params);
  }

}