NewsZ, a Mediawiki extension that allows users to create frames and tables with
lists of News per Category, limited to n elements, with thumbnails preview.

Rev. 1.0 (2012/01/08)

This extension search the first tag "Image:" in article and uses it as thumbnails preview;
if the article not contains images, then uses a default "Logo-category_name.png" as thumbnail.
You can chose several types of previews: simple text, icons in colums, icons with text preview.
NewsZ browse also subcategories and may be a true "news aggregator.

How it works
------------
Text preview is extracted from first valid text block (a paragraph) of article. But you can also
use the hidden tags as is explained below:

To mark the text inside article for preview, simply use this syntax:
<!--NewsZ--> ... here the text article preview ... <!--EndZ-->

You can hide a preview text in article:
<!--NewsZ ... here the hidden text for article preview ... EndZ-->

You can also hide a icon preview in every article:
<!-- [[Image:my_hidden_logo.png]] -->

Usage
-----
{{#newsz:My_news_category|begin from|news counter|cols or chars| class="" style="" ...}}

where:
- "My_news_category" = a Category (Note: better replace spaces with underscore)
- "begin from" = 0-->last inserted, 1-->previous, etc.
- "news counter" = number of articles in table
- "cols or chars" = 0 --> list article without thumbnails
                  1 to 15 (-1 to -15) --> n. of cols in preview per table
                  >15 (or >abs(-15))  --> number of char in text article preview
                  negative numbers    --> returns a table with date and article preview
- last field accepts list of styles

Example
-------
{{#newsz:World_news|0|10|140| style="font-size:14px; width:50%;"}}

Installation
------------
- Create NewsZ folder in your $IP/extensions/
- copy NewsZ.php to your $IP/extensions/NewsZ/
- add the following to LocalSettings.php:

require_once( "$IP/extensions/NewsZ/NewsZ.php");
//Add global custom thumbnail size here (default size is "96x72px"):
$wgNewsZthumbsize='120x96px';
//To avoid search articles in first level subcategories add this line:
$wgNewszNotSubCategory = true;
//Date format: default=english(MDY); DMY=latin; YMD=scientific
$wgNewszFormatDate = 'YMD';

Notes
-----
To speed up the news refresh, you can invalidate cache in Localsettings.php:
//$wgCacheEpoch = max( $wgCacheEpoch, gmdate( 'YmdHis', @filemtime( __FILE__ ) ) );
$wgCacheEpoch = gmdate( 'YmdHis' );
