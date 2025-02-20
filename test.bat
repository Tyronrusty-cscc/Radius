@echo off
setlocal
set PHPUNIT_PATH=vendor\bin\phpunit --no-coverage
echo Running tests...
%PHPUNIT_PATH% %1
endlocal 