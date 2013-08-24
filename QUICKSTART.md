# Quickstart

This file contains a detailed explaination on how to use this project.

In order to use this project a you need to have a running Zend Framework 2 
project. For this guide I'm going to run the quick start guide within a 
Symfony HTTP Kernel. If you already have a project you can simply skip the 
first steps.

For a detailed installation guide on Zend Framework 2 have a look at the 
[Getting Started] (http://framework.zend.com/manual/2.2/en/user-guide/skeleton-application.html).

Composer is assumed to run with the "composer" command. (some guides use 
composer.phar).

## Installing Zend Framework 2 Skeleton

Create a zend skeleton project:

```
composer create-project -s dev zendframework/skeleton-application /path/to/install
```

You will be asked the following question:

```
Do you want to remove the existing VCS (.git, .svn..) history?
```

Choose "Y" if you don't know what to do here.

A skeleton application has now been installed.

## Configure webserver

Set /path/to/install/public as your document root.

Check if you get the "Welcome to Zend Framework 2" page

## Install stack-zf2

Require the stack-zf2 project:

```
composer require reenl/stack-zf2:dev-master
```

Now you have all dependencies you require to run stack-zf2.

## Alter index.php

Copy the example index:

```
cp vendor/reenl/stack-zf2/public_example/index.php public/index.php
```

## Now play

You project is now compatible with the Symfony HTTP kernel. This has a lot of 
advantages, one of them is using [Stack] (http://stackphp.com/). In order to 
do that you should edit the `public/index.php` file.
