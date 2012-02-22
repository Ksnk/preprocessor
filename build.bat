@echo off
rem 
rem   you can use batch file for building Backup-script
rem     build.bat  without parameters compile all target
rem     build.bat allinone - just to build only one target
rem 
rem 
rem   Make shure, this is correct path to you PHP interpreter and main preprocessor file
rem 
if "%PHPBIN%" == ""  set PHPBIN=Z:\usr\local\php5\php.exe
if "%PROCESSOR%"=="" set PROCESSOR=build\preprocessor.php

rem 
rem   so let's go!
rem 

if not exist "%PROCESSOR%" goto prebuild


if not "%1"=="" goto next

rem 
rem  target to make all at once
rem 
set PAR=init,phing

:dolist

for %%i in (%PAR%) do call :%%i

goto fin

:next
if "%1"=="" goto fin
call :%1

shift
goto next

rem
rem You can place your targets here
rem


:prebuild

echo building from original sources
%PHPBIN% -q  src/preprocessor.php /Ddst=build /Dtarget=release config.xml
exit /b 0


:init

echo building init
%PHPBIN% -q  %PROCESSOR% /Ddst=build /Dtarget=release config.xml
exit /b 0

:phing

echo building phing
%PHPBIN% -q  %PROCESSOR% /Ddst=build /Dtarget=phing config.phing.xml
exit /b 0

rem
rem errors
rem
:no_preprocessor
echo Preprocessor file not found (%PROCESSOR%). 
goto fin

:fin