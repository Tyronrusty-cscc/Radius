@echo off
setlocal
set PHPUNIT_PATH=k:\data\CodeIgniter-4.4.5-Shared\vendor\bin\phpunit --no-coverage
echo Running tests...
%PHPUNIT_PATH% %1
endlocal 