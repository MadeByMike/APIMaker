# API Maker

A PHP class that helps you make an API like interface for any database table.

It allows quick configuration using XML files. The XML rules files contain simple instructions for handling requests and formatting results.

Results can be returned in JSON format or using a template engine such as [Mustache](http://mustache.github.io/mustache.5.html). Mustache is included as the default template engine, although API Maker will work with any template engine.

## Included Examples

*   HTML basic
*   HTML with search
*   JSON basic
*   XML basic

## Setup

Extract the sample code to your desired location.

Along with the index.php file containing these instructions there should be a folder named 'API' and another named 'APIMaker'. There should also be a example.sql file and a API.php file.

Configure the api_config.php in the 'APIMaker' folder so that it contains the details required to connect to your database.

Import the example.sql file into a table named 'example' to work with the provided sample rules files.

Alternatively configure some rules files to work with your existing database.

Examples in the 'API' folder show how you can rewrite the url to hide the rules parameter. The API.php file contains the most basic implementation.

### Config file

The config file specifies the database connection details, the default error message, the directory containing the rules files and a switch for turning on debugging.

The default error message will only be displayed if there is no error message defined in the rules file, or when a rules file is invalid or cannot be found.

If APP_DEGUB is true adding a 'debug' to the requests GET or POST data will show additional information via the JavaScript console. This should always be FALSE in production.

### Rules files

Rules files are xml files that provide instructions for querying the database, handling user input and presenting results.

The application can be instructed which rules file to use by passing the parameter 'rules' via GET or POST data.

API Maker will look for a matching xml rules file in the location specified in the api_config.php file.

To work correctly the minimum requirements for a rules file are:

*   a config element.
*   a 'table' attribute on the config element.
*   a filter element containing at least one group.

That's it, you don't even need to have anything inside your group. Take a look at the minimal.xml rules file as an example. You don't even need the template if you don't want to show the results, however that would be very boring and useless.

Take a look at the blank-everything.xml file. This contains every possible element and attribute, although none of the values required for them to work.

#### Specifying the database table

This is as easy as setting the 'table' attribute on the config element of the rules file.

Currently you can only specify a single table.

#### Selecting results

The 'filter' element controls the selection of results. You can think of it like an xml representation of the WHERE part of an SQL statement. Inside the filter element are one or more 'group' elements. Each group element has an operator attribute or will default to use 'AND'. Groups can be nested.

Group elements contain 'fields' that specify the parameters for the resulting database query. 'objectName' is the database column and 'condition' specifies the type of comparison. Options include:

*   equals
*   notEquals
*   contains
*   notContains
*   greaterThan
*   greaterThanOrEqual
*   lessThan
*   lessThanOrEqual
*   startsWith
*   endsWith

To specify a value for comparison include either a 'defaultValue' or 'formName'. The value of the formName tells the API maker to look for GET or POST data with the matching name to use in the query. If it can't be found the defaultValue will be used.

If there is no defaultValue and a formName is not given the field will be dropped from the formulated query.

#### Limiting results

When working with large tables you will want to limit the results returned. There are two ways you can do this.

One is to set a 'resultsPerPage' attribute on the config element. This will limit the number of results that can be returned and will allow the use of a 'page' parameter that can be in POST and GET requests.

The other method is to set a 'recordsAllowed' attribute on the config element. This will limit the results returned but not allow paging.

You cannot use both methods at the same time.

#### WithResult functions

Whilst a template engine can handle most of the formatting, sometimes you might want to modify the result before passing it to the template engine.

For example you might want to parse a date into a particular format or change a numeric value into say 'High', 'Med, 'Low'.

To do this the API Maker includes 4 functions:

*   replace
*   formatDate
*   jsonEncode
*   htmlEscape

The functions are defined with the field element where the 'objectName' represents that database column name, the 'function' element tells the API Maker what to do with this field. Some functions require additional elements to control the output.

The replace function required a additional 'find' and 'replace' elements and the formatDate function requires a 'format' element containing a php date format.

#### Controlling the output

Once the results are collected from the database they are passed along with the contents of the template element in the rules file, to the template engine which substitutes the results and generate the desired output.

In most cases the output is controlled via the template engine, however if you want to return JSON you can specify this by setting the 'format' attribute of the config element to 'json'. This will bypass the template engine completely and directly parse the results object into JSON.

In addition you can set the 'mime' attribute to control the content type set in the http header.

If you need further control over the output you can pass a custom function to the template engine. And insert any alternative template system or your own functions.

```
<?php 
	require './APIMaker/APIMaker.class.php';
	$api_maker = new APIMaker(array('engine'=>'custom_engine'));
	function custom_engine($template, $results){
		//Do stuff here
	}
?>
```

You can also get the result object or the template results:

```
<?php 
	$api_maker = new APIMaker(array('echo'=>false)); // hush!
	$results = $api_maker->results;
	$results = $api_maker->template_results;
?>
```

#### Sorting

The 'sort' element specifies a 'objectName' and 'sortDirection' to specify a column name and sort order.

sortDirection must be either ASC or DESC

#### Handling Errors

The errorMsg element tells the API paker to display this message when no results are found or there is a general error.