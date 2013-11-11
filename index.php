<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="Mike">

  <title>API Maker examples</title>

  <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.2/css/bootstrap.min.css">
	
</head>
<body>
    <div class="container">
		<h1>API Maker</h1>
		<p>A PHP class that helps you make an API like interface for any database table.</p>
		<p>It allows quick configuration using XML files. The XML rules files contain simple instructions for handling requests and formatting results.</p>
		<p>Results can be returned in JSON format or using a template engine such as <a href="http://mustache.github.io/mustache.5.html">Mustache</a>. Mustache is included as the default template engine, although API Maker will work with any template engine.</p>

		<h2>Examples</h2>
		<ul>
			<li><a href="./API/HTML/html-basic">HTML basic</a></li>
			<li><a href="./API/HTML/html-search">HTML with search</a></li>
			<li><a href="./API/JSON/JSON-basic">JSON basic</a></li>
			<li><a href="./API/XML/XML-basic">XML basic</a></li>
		</ul>

		<h2>Setup</h2>
		<p>Extract the sample code to your desired location.</p>
		<p>Along with the index.php file containing these instructions there should be a folder named 'API' and another named 'APIMaker'. There should also be a example.sql file and a API.php file.</p>
		<p>Configure the api_config.php in the 'APIMaker' folder so that it contains the details required to connect to your database.</p>
		<p>Import the example.sql file into a table named 'example' to work with the provided sample rules files.</p>
		<p>Alternatively configure some rules files to work with your existing database.</p>
		<p>Examples in the 'API' folder show how you can rewrite the url to hide the rules parameter. The API.php file contains the most basic implementation.</p>

		<h3>Config file</h3>
		<p>The config file specifies the database connection details, the default error message, the directory containing the rules files and a switch for turning on debugging.</p>
		<p>The default error message will only be displayed if there is no error message defined in the rules file, or when a rules file is invalid or cannot be found.</p>
		<p>If APP_DEGUB is true adding a 'debug' to the requests GET or POST data will show additional information via the JavaScript console. This should always be FALSE in production.</p>

		<h3>Rules files</h3>
		<p>Rules files are xml files that provide instructions for querying the database, handling user input and presenting results.</p>
		<p>The application can be instructed which rules file to use by passing the parameter 'rules' via GET or POST data.</p>
		<p>API Maker will look for a matching xml rules file in the location specified in the api_config.php file.</p>
		<p>To work correctly the minimum requirements for a rules file are:</p>
		<ul>
			<li>a config element.</li>
			<li>a 'table' attribute on the config element.</li>
			<li>a filter element containing at least one group.</li>
		</ul>
		<p>That's it, you don't even need to have anything inside your group. Take a look at the <a href="./API.php?rules=minimal">minimal.xml</a> rules file as an example. You don't even need the template if you don't want to show the results, however that would be very boring and useless.</p>
		<p>Take a look at the blank-everything.xml file. This contains every possible element and attribute, although none of the values required for them to work.</p>
		<h4>Specifying the database table</h4>
		<p>This is as easy as setting the 'table' attribute on the config element of the rules file.</p>
		<p>Currently you can only specify a single table.</p>

		<h4>Selecting results</h4>
		<p>The 'filter' element controls the selection of results. You can think of it like an xml representation of the WHERE part of an SQL statement. Inside the filter element are one or more 'group' elements. Each group element has an operator attribute or will default to use 'AND'. Groups can be nested.</p>
		<p>Group elements contain 'fields' that specify the parameters for the resulting database query. 'objectName' is the database column and 'condition' specifies the type of comparison. Options include:</p>
		<ul>
			<li>equals</li>
			<li>notEquals</li>
			<li>contains</li>
			<li>notContains</li>
			<li>greaterThan</li>
			<li>greaterThanOrEqual</li>
			<li>lessThan</li>
			<li>lessThanOrEqual</li>
			<li>startsWith</li>
			<li>endsWith</li>
		</ul>
		<p>To specify a value for comparison include either a 'defaultValue' or 'formName'. The value of the formName tells the API maker to look for GET or POST data with the matching name to use in the query. If it can't be found the defaultValue will be used.</p> 
		<p>If there is no defaultValue and a formName is not given the field will be dropped from the formulated query.</p> 
		<h4>Limiting results</h4>
		<p>When working with large tables you will want to limit the results returned. There are two ways you can do this.</p>
		<p>One is to set a 'resultsPerPage' attribute on the config element. This will limit the number of results that can be returned and will allow the use of a 'page' parameter that can be in POST and GET requests.</p>
		<p>The other method is to set a 'recordsAllowed' attribute on the config element. This will limit the results returned but not allow paging.</p>
		<p>You cannot use both methods at the same time.</p>

		<h4>WithResult functions</h4>
		<p>Whilst a template engine can handle most of the formatting, sometimes you might want to modify the result before passing it to the template engine.</p>
		<p>For example you might want to parse a date into a particular format or change a numeric value into say 'High', 'Med, 'Low'.</p>
		<p>To do this the API Maker includes 4 functions:</p>
		<ul>
			<li>replace</li>
			<li>formatDate</li>
			<li>jsonEncode</li>
			<li>htmlEscape</li>
		</ul>
		<p>The functions are defined with the field element where the 'objectName' represents that database column name, the 'function' element tells the API Maker what to do with this field. Some functions require additional elements to control the output.</p>
		<p>The replace function required a additional 'find' and 'replace' elements and the formatDate function requires a 'format' element containing a php date format.</p>

		<h4>Controlling the output</h4>
		<p>Once the results are collected from the database they are passed along with the contents of the template element in the rules file, to the template engine which substitutes the results and generate the desired output.</p>
		<p>In most cases the output is controlled via the template engine, however if you want to return JSON you can specify this by setting the 'format' attribute of the config element to 'json'. This will bypass the template engine completely and directly parse the results object into JSON.</p>
		<p>In addition you can set the 'mime' attribute to control the content type set in the http header.</p>
		<p>If you need further control over the output you can pass a custom function to the template engine. And insert any alternative template system or your own functions.</p>

		<pre><code>&lt;?php <br/>require './APIMaker/APIMaker.class.php';<br/>$api_maker = new APIMaker(array('engine'=&gt;'custom_engine'));<br/> function custom_engine($template, $results){<br/>	//Do stuff here<br/> }<br/>?&gt;</code></pre>

		<p>You can also get the result object or the template results:</p>

		<pre><code>&lt;?php <br/>$api_maker = new APIMaker(array('echo'=&gt;false)); // hush!<br/>$results = $api_maker-&gt;results;<br/>$results = $api_maker-&gt;template_results;<br/>?&gt;</code></pre>

		<h4>Sorting</h4>
		<p>The 'sort' element specifies a 'objectName' and 'sortDirection' to specify a column name and sort order.</p>
		<p>sortDirection must be either ASC or DESC</p>

		<h4>Handling Errors</h4>
		<p>The errorMsg element tells the API paker to display this message when no results are found or there is a general error.</p>
	</div>
</body>
</html>