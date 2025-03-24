<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class CSCCSAMLAuth extends BaseConfig
{
	// Configuration for the Codeigniter 4 SimpleSAML Authentication and Authorization Controller filter.  
    public $serviceProvider  = 'default-sp';
	public $notAuthorizedRedirect  = '/home/notauthorized';
	public $permissionsFilePath = APPPATH . 'Config\permissions.usr';
}