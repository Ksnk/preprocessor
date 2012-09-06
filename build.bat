@echo off
:: **********************************************************************
::   you can use batch file for building preprocessor
::     build.bat  without parameters - compile all target
::     build.bat prebuild - just to build only one target
::
::   Make shure, this is correct path to you PHP interpreter and main preprocessor file
::
:: looks like it's a good idea to place the strings
::set PHPBIN=z:\usr\local\php5\php.exe
::set PROCESSOR=d:\projects\preprocessor\build\preprocessor.php
:: up into directory tree and place int file
::  env.bat
::
if exist ..\env.bat call ..\env.bat

:: anyway, let's setup default variables
::
if "%PHPBIN%"==""    set PHPBIN=Z:\usr\local\php5\php.exe
if "%PROCESSOR%"=="" set PROCESSOR=build\preprocessor.php

::  **********************************************************************
::   so let's go!
:: 
::  in case the first build, create it by running prebuild target
if not exist "%PROCESSOR%" goto prebuild


if not "%1"=="" goto next

:: **********************************************************************
::  list all target to make in case of run without parameters
:: 
set PAR=init

:dolist

for %%i in (%PAR%) do call :%%i

goto fin

:next
if "%1"=="" goto fin
call :%1

shift
goto next

::  **********************************************************************
:: You can place your targets here
::


:prebuild
::
:: this target build preprocessor by using sources itself. 
::
echo building from original sources
%PHPBIN% -q  src/preprocessor.php /Ddst=build /Dtarget=release config.xml
exit /b 0


:init
::
:: this target build preprocessor by using alredy builded preprocessor.
::
echo building init
%PHPBIN% -q  %PROCESSOR% /Ddst=build /Dtarget=release config.xml
exit /b 0

:phing
::
:: this target copied preprocessor files into phing catalogue.
::
echo building phing
%PHPBIN% -q  %PROCESSOR% /Ddst=build /Dtarget=release config.xml
echo on
xcopy build\phing\*.* z:\usr\local\php5\pear\pear\phing\tasks\ext\preprocessor  /Y/E
exit /b 0

:: **********************************************************************
:: some errors
::
:no_preprocessor
echo Preprocessor file not found (%PROCESSOR%). 
goto fin

:fin