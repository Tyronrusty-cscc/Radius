<?php
use PHPUnit\Framework\TestCase;
use CodeIgnitor\Config\Services;


class DummyTest extends TestCase
{
    protected $controller;
    protected $request;
    protected $response;
protected function setUp(): void
{
parent::setUp();
$this->controller = new App\Controllers\Users();
$this->request = \Config\Services::request();
$this->response = \Config\Services::response();

}
public function testCondition()
{
$result = $this-> controller->index();
$this->assertInstanceOf(CodeIgnitor\HTTP\ResponseInterface::class, $result);
$output =$result->getBody();
$this->assertStringContainString('<h1>Login</h1>',$output);
}
}