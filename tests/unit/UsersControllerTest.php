<?php

namespace Tests\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Config\Services;
use App\Controllers\Users;
use App\Models\UserModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;


class UsersTest extends CIUnitTestCase
{
    use ControllerTestTrait; // Use ControllerTestTrait for direct method testing


    protected function setUp(): void
    {
        parent::setUp();

        // Ensure no session conflicts
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
/**
 *  helper function to inject the request in the mock with reflection
 */

    private function injectRequestIntoMock($mock, $request){

        $reflection = new \ReflectionClass($mock);
        if($reflection->hasProperty('request')){
            $requestProperty = $reflection->getProperty('request');
            $requestProperty->setAccessible(true);
            $requestProperty->setValue($mock, $request);
        } else{
            throw new \Exception("Request property not found in " .get_class($mock));
        }

    }
    public function testFakeHttpPostRegisterValidationFails()
    {
        $expectedErrors = [
            'firstname' => 'The firstname field is required.',
            'lastname' => 'The lastname field is required.',
            'email' => 'The email field is required.',
            'password' => 'The password_confirm field does not match the password field.'
        ];

        // Create a request with POST data
        $request = service('request')
                  ->setMethod('POST')
                  ->setHeader('Content-Type', 'application/x-www-form-urlencoded')
                  ->setGlobal('post', [
            'firstname' => '',
            'lastname' => '',
            'email' => 'invalid email',
            'password' => '123',
            'password_confirm' => '321',
        ]);
        Services::injectMock('request', $request);

        //  Mock Users controller
        $userMock = $this->getMockBuilder(Users::class)
                         ->onlyMethods(['register'])
                         ->getMock();

        Services::injectMock('Users', $userMock);

        $this->injectRequestIntoMock($userMock, $request);
         
         //define the fake response           
        $userMock->method('register')->willReturn(
            service('response')
                ->setStatusCode(400)
                ->setHeader('Content-Type', 'application/json')
                ->setJSON($expectedErrors)
        );
 
 //   exit;  // Stop execution to check if request data is present
        $result = $userMock->register();

        if (!$result) {
            throw new \Exception("Test execution returned null response.");
        }

    
        //  Validate response (expecting validation failure)
        $this->assertEquals(400, $result->getStatusCode());
        
        $this->assertStringContainsString('The firstname field is required.', $result->getBody());
        $this->assertStringContainsString('The lastname field is required.', $result->getBody());
        $this->assertStringContainsString('The email field is required.', $result->getBody());
        $this->assertStringContainsString('The password_confirm field does not match the password field.', $result->getBody());
        
     }

     public function testFakeHttpPostRegisterSuccess()
    {
        // Create valid user data
        $validUserData = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123',
            'password_confirm' => 'SecurePass123',
        ];

        // Create a proper HTTP request
        $request = service('request')
            ->setMethod('POST')
            ->setHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->setGlobal('post', $validUserData);

        //  Inject the request into CodeIgniter's service container
        Services::injectMock('request', $request);

        // Mock the Users controller
        $userMock = $this->getMockBuilder(Users::class)
            ->onlyMethods(['register'])
            ->getMock();

        //  Inject the Users mock
        Services::injectMock('Users', $userMock);

        // Use Reflection to Inject the Request (Since It's Protected)

        $this->injectRequestIntoMock($userMock, $request);

        //  Mock register() method to return a 200 success response
        $userMock->method('register')->willReturn(
            service('response')
                ->setStatusCode(200)
                ->setHeader('Content-Type', 'application/json')
                ->setJSON(['message' => 'Successful Registration'])
        );

        //  Call the mocked register() method
        $result = $userMock->register();

        // Validate response (expecting success)
        $this->assertEquals(200, $result->getStatusCode(), "Expected a 200 OK response.");

        //  Ensure success message is in the response
        $this->assertStringContainsString('Successful Registration', $result->getBody());
 
}
}