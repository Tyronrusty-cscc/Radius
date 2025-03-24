<?php
// SAML Authentication implemented as a CodeIgniter 4 controller filter.  

namespace App\Filters;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SAMLAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
    log_message('debug', "*** CSCC SAML Authentication and Authorization Filter Starting ***");
    // Retrieve the configuration settings.  
    $config = new \Config\CSCCSAMLAuth();
	
	if (!isset($config->serviceProvider)) {
	   throw new \exception ("CSCCSAMLAuth Configuration Error - Property: serviceProvider not specified in the configuration file: " . APPPATH . "Config\\CSCCSAMLAuth.php");
	   }
	
	if (!isset($config->notAuthorizedRedirect)) {
       throw new \exception ("CSCCSAMLAuth Configuration Error - Property: notAuthorizedRedirect not specified in the configuration file: " . APPPATH . "Config\\CSCCSAMLAuth.php");	   
       }

	if (!isset($config->permissionsFilePath)) {
	   throw new \exception ("CSCCSAMLAuth Configuration Error - Property: permissionsFilePath not specified in the configuration file: " . APPPATH . "Config\\CSCCSAMLAuth.php");	   
	   }
	   
    if (!file_exists($config->permissionsFilePath)) {
	   throw new \exception ("CSCCSAMLAuth Configuration Error - The Permissions File: " . $config->permissionsFilePath . " does not exist.  This setting is specified in the configuration file: " . APPPATH . "Config\\CSCCSAMLAuth.php");	   
	   }
	
	log_message('debug', "The configuration was loaded successfully"); 	
	if(session_status()===PHP_SESSION_NONE){
		session_start();
	}
	//session_start();
	if (isset ($_SESSION['portal']['return_url'])) {
	   define ('APP_PORTAL_RETURN_URL', $_SESSION['portal']['return_url']);
	   }
	   
	try {
		log_message('debug', "Instantiating new \SimpleSAML\Auth\Simple(\"" . $config->serviceProvider . "\")");

		$saml = new \SimpleSAML\Auth\Simple($config->serviceProvider);
			
		if (!isset ($_SESSION['SimpleSAMLphp_SESSION'])) log_message('debug', "Attempting to authenticate to SAML via Service Provider: " . $config->serviceProvider );
	    $saml->requireAuth();
		log_message('debug', "Successfully authenticated user SAML via Service Provider: " . $config->serviceProvider );		
		$samlattributes = $saml->getAttributes();
    }
catch (Exception $e)
	{	
    // SAML authentication failed, log the error and report a generic message to the user. 
    throw new \exception ("SAML Authentication failed, reason: ". $e->getMessage());
	#die ("<p class=\"alert alert-danger\">SAML Authentication Failed, contact our help desk immediately</p>");	
	}
	
	if (isset ($samlattributes['samaccountname'][0])) {
    $app_login_name = $samlattributes['samaccountname'][0];
	define ('APP_LOGIN_NAME', $app_login_name);
    log_message('debug', "SAML token samaccountname indicates user: $app_login_name");
    }
else
    {
	throw new \exception ("The SAML token does not contain samaccountname attribute"); 
    }	

	// Retrieve the group memberships. 
	$memberof = array();
	if (isset($samlattributes['MemberOf'])) {
		$memberof = $samlattributes['MemberOf'];
		for ($x = 0; $x < count($memberof); $x++) {
    	 	$memberof[$x] = strtolower(trim($memberof[$x]));
		    }
	    }

    define ('APP_MEMBEROF', $memberof);
	define ('APP_CSCCID', $this->Get_SAML_Attribute ($samlattributes, 'http://cscc/claims/EmployeeID'));
	define ('APP_DN', strtolower(trim($this->Get_SAML_Attribute ($samlattributes, 'dn'))));
    define ('APP_GIVENNAME', $this->Get_SAML_Attribute ($samlattributes, 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname'));
    define ('APP_SURNAME', $this->Get_SAML_Attribute ($samlattributes, 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname'));
    define ('APP_LOCATION', $this->Get_SAML_Attribute ($samlattributes, 'http://cscc/claims/location'));
    define ('APP_DEPARTMENT', $this->Get_SAML_Attribute ($samlattributes, 'http://cscc/claims/department'));
    define ('APP_TITLE', $this->Get_SAML_Attribute ($samlattributes, 'http://cscc/claims/title'));
    define ('APP_PHONE', $this->Get_SAML_Attribute ($samlattributes, 'http://cscc/claims/telephoneNumber'));
    define ('APP_FAX', $this->Get_SAML_Attribute ($samlattributes, 'Fax'));
	define ('APP_EMAIL', $this->Get_SAML_Attribute ($samlattributes, 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'));
	define ('APP_SAML_LOGOUT_URL', $saml->getLogoutURL());
	
    try {	
        log_message('debug', "Initiating SimpleSAMLphp session cleanup");			
        $session = \SimpleSAML\Session::getSessionFromRequest();
        $session->cleanup();	
		session_write_close();  // This is necessary to allow Codeigniter 4 to issue an INI_SET and specify a new write path when you use the codeigniter session object to read or write session data.
		log_message('debug', "SimpleSAMLphp session cleanup completed");			
	    }
  catch (Exception $e)
		{	
		throw new \exception ("SimpleSAMLphp session cleanup method failed, reason: ". $e->getMessage());
		}

	// Determine the current controller and method.  
	$router = service('router'); 
	$controller  = $router->controllerName();  

	// Now make sure that the controller is of type string, if not, it's a closure (anonymous function)
	// defined as a route in the routes.php file.  
	if (gettype($controller) == 'string') {
	   $controller = strtolower(trim($controller));
	   if (substr ($controller, 0, 17) == '\app\controllers\\') {
		  // Remove the \app\controllers\ namespace.
		  $controller = substr($controller, 16);
	      }
 	   $method = $router->methodName();
	   }
  else 
       { 
       // The route is to an anonymous object, use the first segment after index.php/ as the 
	   // controller and the second segment after index.php/ as the method.  If there is no
	   // segment following the index.php/ then make the controller and method both anonymous.
	   // If there is only one segment following the index.php/, use it's value for the controller
	   // and default the method to anonymous.  
	   $url = trim(current_url());
	   $pos = strpos(strtolower($url), 'index.php/');
       if ($pos === false) {
		  $controller = '\\' . 'anonymous';
		  $method = 'anonymous';
	      }
	 else 
	      {
          $mc = substr($url, $pos + 10);
		  $segments = explode('/', $mc);
          if (count ($segments) > 0 && trim($segments[0]) != '') 
		     $controller = '\\' . trim($segments[0]);
	    else
		     $controller = 'anonymous';

 	  	  if (count ($segments) > 1 && trim($segments[1]) != '') 
			 $method = trim($segments[1]);
  	    else
		     $method = 'anonymous';
		  }
       }

	$current_controller = strtolower(trim($controller) . '\\' . trim($method));

    // Retrieve the list of permissions from the Permissions.usr file.
	$permissions = $this->get_permissions (APP_LOGIN_NAME, APP_MEMBEROF, $config->permissionsFilePath);
	define ('APP_PERMISSIONS', $permissions);
    log_message('debug', "User: ". APP_LOGIN_NAME . " has these permissions: [" . implode (', ', $permissions) . "].");	

    // Now see if the user is authorized to the current controller / method based on his Active Directory
	// group memberships.  

	log_message('debug', "Determining if user: " . APP_LOGIN_NAME . " is authorized to controller: " . $current_controller);	
	$authorized = false;
	foreach ($permissions as $permission) {
		if (substr($permission, -1) == '*' && strlen($permission) > 1) {
			// Wildcard match, \* will grant access to the whole application.  
			log_message('debug', "Wildcard controller specified: $permission");	        

			if (substr($current_controller, 0, strlen($permission) -1) == substr($permission, 0, strlen($permission) -1)) {
				log_message('debug', "User: ". APP_LOGIN_NAME . " is authorized to controller: " . $current_controller . " through the wildcard permission: $permission");	        
			    $authorized = true;
				break;
			    }
		    }
		elseif ($permission == $current_controller) {
			    log_message('debug', "User: " . APP_LOGIN_NAME . " is authorized to controller: " . $current_controller);				
			    $authorized = true;
				break;
			    }
	    }
	
	if ($authorized == false) {
        log_message('debug', "User: ". APP_LOGIN_NAME . " is NOT authorized to controller: " . $current_controller . ", setting Authorized property to False.");	        
        log_message('debug', "*** CSCC SAML Authentication and Authorization Filter Stopping ***");		
		return redirect()->to($config->notAuthorizedRedirect);
	    }
		
    log_message('debug', "*** CSCC SAML Authentication and Authorization Filter Stopping ***");		
	return Null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do something here
    }

private function Get_SAML_Attribute ($samlattributes, $attribute, $return_as_array = false) {
	// Returns saml attribute zero index or '' length string if the attribute is not available.
	// Uncomment the lines below if you would like the function to terminate the program if the SAML assertion payload does not 
    // contain the requested attribute.  
#	if (!isset ($samlattributes[$attribute]))
#		die ("<p class=\"alert alert-danger\">The SAML assertion payload received by service provider: ". htmlspecialchars(APP_SAML_SERVICE_PROVIDER) . " does not inclue the attribute: " . htmlspecialchars($attribute) . "</p>");	
		
	if (isset ($samlattributes[$attribute]) && count($samlattributes[$attribute]) > 0) {
	 if ($return_as_array) 
		  return $samlattributes[$attribute];
	 else 
	      return $samlattributes[$attribute][0];
	   }
  else 
       {
	   return '';
       }
    }
	
private function Get_Permissions ($user = '', $memberof = [], $file_name = '') {
    /* 
    This function opens up $file_name and returns a string array containing the permissions 
	for $user.  You must pass the current group membership in an array
    as the $memberof parameter, the contents of which must be in domain\cn format as is 
	returned in the SimpleSAMLphp attribute payload.
	
	This function will determine application permissions for the user based
	on default permissions specified by (*), and group membership.  An explicit assignment of 
	permissions to a user can also be performed.  
		
	Permissions can be grouped into roles with the role= directive followed by a role name and any number of permissions. 
	For example:
	role=admin,add,edit,browse,delete.  These role definitions must occure prior to being referenced
	in permission assignments (meaning, put them at the top of the file), and can't be nested.  To assign a role, use the 
	format role=<role> when assigning global permissions (*), group=, or an explicit user permission asignment.
			
 	To specify group security, use the format group=<group name>,<permission1>,<permission2>,role=<role>
	in the security configuration file. 
	
	To specify global permissions for all users, use the format: *,<permission1>, <permission2>, <role=role name>
	If user is not found, the rights specified as the default rights *,<permission1>, etc will be returned.
	
	To assign explicit permissions to a user, use the format <SAMAccountName>,<permission1>, <permission2>, <role=role name>
	
    If no permissions can be found, an empty array [] will be returned.  
	
	Please note that permissions are cumulative unless user specific permissions are specified.  
	For example: if you give everyone (*) A and B rights, and the group test1
	D and E, and group the test2 C rights, any member of the group test will have ABCDE.
	If you assign rights to a user for example: test,HIJK, the user test will 
	have HIJK rights, regardless of the default rights or group membership of the user. 
	
    Format of security.usr file.  Note that individual permissions assignments 
	to a user override group permission assignments.  
    <user>, <Permissions>
	role=<rolepermission1>, <rolepermission2>
	GROUP=<Group Name>, <Permission1>, <Permission2>, <role=role name>
    *,<permission1>, <Permission2>, <Role=role name>
	someuser,<permission1>, <permission2>, <role=role name>
	
	*/ 
	$user = strtolower(trim($user));
	$permissions = [];
	$roles = [];

	if ($user == '') 
	   throw new \exception("ERROR: No user parameter provided to Get_Permissions function");
 
	if ($file_name == '') 
       throw new \exception("ERROR: File name provided to Get_Permissions function");

    if (! file_exists ($file_name))
	   throw new \exception ("ERROR: The permissions file: $file_name does not exist (Get_Permissions function)");

	// Open the file and read each line to determine the permissions of the user.   
	$fp = fopen ($file_name, 'r');
	while (! feof($fp)) {
	   $rec = strtolower (trim (fgets($fp, 1024)));
	   
	   if ($rec == '') continue; 
	   
	   // Filter out the comments.
	   if (! (strpos ("#'",substr ($rec, 0, 1)) === false)) continue;

       $ary1 = explode (',', $rec);
	   $ary = [];
	   // Remove leading and triling spaces and all blank elements.
	   for ($x = 0; $x < count($ary1); $x++) {
		   $a = trim($ary1[$x]);
		   if ($a != '') {
		      $ary[] = $a;
		      }
	       }

  	   if (count($ary) < 2) continue;
		   
	   $u = $ary[0];

	   // Handle the default permissions *.  If the permission is not already in the $permissions array, add it.
       if ($u == '*') {
		   for ($x = 1; $x < count($ary); $x++) {
			  log_message('debug', "Processing global permission: " . $ary[$x]);
			  if (substr($ary[$x], 0, 5) == 'role=') {
				  $role = trim(substr($ary[$x], 5));
				  // Add the individual permissions from the role array to the permissions array if necessary.  
			      if (isset ($roles[$role])) {
					  foreach ($roles[$role] as $r) {
						 if (in_array($r, $permissions) == false) {
							 $permissions[] = $r;
							 log_message('debug', "Adding permission for role: $role: $r");
							 }
						 }
				     }
				else 
				     {
					 log_message('error', "Role: $role not defined in file: $file_name");
					 }
		          }
			 else 
			      {
				  if (in_array($ary[$x], $permissions) == false) {
					 $permissions[] = $ary[$x];
					 }
				  }
			  }	      
		  }
		  
		  if ($u == $user) {
		   $permissions = [];  // Specific user permissions override al other permissions.  
    	   for ($x = 1; $x < count($ary); $x++) {
			  log_message('debug', "Processing explicit user assigned permission: " . $ary[$x]);
			  if (substr($ary[$x], 0, 5) == 'role=') {
				  $role = trim(substr($ary[$x], 5));
				  // Add the individual permissions from the role array to the permissions array if necessary.  
			      if (isset ($roles[$role])) {
					  foreach ($roles[$role] as $r) {
						 if (in_array($r, $permissions) == false) {
							 $permissions[] = $r;
							 log_message('debug', "Adding permission for role: $role: $r");
							 }
						 }
				     }
				else 
				     {
   					 log_message('error', "Role: $role not defined in file: $file_name");
					 }
		          }
			 else 
			      {
				  if (in_array($ary[$x], $permissions) == false) {
					 $permissions[] = $ary[$x];
					 }
				  }
			  }
 		   break; 
   		   }
 
          // If the line specified is a group, see if the user is a member 
		  // and append the permissions associated with the group to permissions
		  // that the user already has.  
		  
		  if (substr($u, 0, 6) == 'group=') {
			  $group = trim(substr($u, 6));
			  if (in_array ($group, $memberof) == true){
				  
			      // Append the group permissions.  
			      for ($x = 1; $x < count($ary); $x++) {
			   		  log_message('debug', "Processing group: $group assiged permission: " . $ary[$x]);
			          if (substr($ary[$x], 0, 5) == 'role=') {
				          $role = trim(substr($ary[$x], 5));
				          // Add the individual permissions from the role array to the permissions array if necessary.  
			              if (isset ($roles[$role])) {
					         foreach ($roles[$role] as $r) {
						         if (in_array($r, $permissions) == false) {
							        $permissions[] = $r;
         							log_message('debug', "Adding permission for role: $role: $r");
							        }
						         }
				             }
				        else 
      				         {
      	 				     log_message('error', "Role: $role not defined in file: $file_name");
					         }
		                 }
			        else 
     	 	 	         {
			     	     if (in_array($ary[$x], $permissions) == false) {
				    	    $permissions[] = $ary[$x];
					        }
				         }
			          }
			      }
		      }
		  
          // Add a new element to the roles array containing a list of all permissions defined for the role.  	  
    	  if (substr($u, 0, 5) == 'role=') {
			  $role = trim(substr($u, 5));
			  log_message('debug', "Processing role: $role.");
			  if (isset ($roles[$role])) {
			      throw new \exception ("ERROR: The role: $role has already been defined (Get_Permissions function)"); 
			  }
			  
			  $permissions_list = [];
			  
			  // Assign the roles to the role permissions_list array.  
			  for ($x = 1; $x < count($ary); $x++) {
			      if (!in_array ($ary[$x], $permissions_list)) {
    		         $permissions_list[] = $ary[$x];
				     }
			      }
				  
			  $roles[$role] = $permissions_list;
			  log_message('debug', "Role: $role defined with the following permissions: [" . implode(', ', $permissions_list) . "].");
		      }
	  
	  
	  }   # While
    fclose ($fp);

    return $permissions;
    }
	
}
?>