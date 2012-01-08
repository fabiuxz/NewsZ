<?php
# NewsZ mediawiki extension Copyright (C) 2012 Fabio Zorba <zoros3000@gmail.com>
# derivate from News.php Copyright (C) 2009 Erich Steiger <me@erichsteiger.com>
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License along
# with this program; if not, write to the Free Software Foundation, Inc.,
# 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
# http://www.gnu.org/copyleft/gpl.html

if( !defined( 'MEDIAWIKI' ) )
  die( -1 );

$wgExtensionFunctions[] = 'wfNewsZ';
$wgHooks['LanguageGetMagic'][] = 'wfNewsZ_Magic';

$wgExtensionCredits['parserhook'][] = array(
	'name' => 'NewsZ',
	'description' => 'Allows users to create tables with lists of News per Category, limited to n elements. With thumbnails preview!',
	'author' => 'Fabio Zorba',
	'url' => 'http://www.mediawiki.org/wiki/Extension:NewsZ',
	'version' => '1.0 20120107',
	'type' => 'parserhook'
);

function wfNewsZ_Magic( &$magicWords, $langCode ) {
	# Add the magic word
	# The first array element is case sensitive, in this case it is not case sensitive
	# All remaining elements are synonyms for our parser function
	$magicWords['newsz'] = array( 0, 'newsz' );
	# unless we return true, other parser functions extensions won't get loaded.
	return true;
}

function wfNewsZ() {
	global $wgParser;
	$wgParser->setFunctionHook( 'newsz', 'fzRenderNewsList' );
}

function fzShortenText($text, $chars) {
	if (strlen ($text) >= $chars) $text = substr($text,0,$chars) . "...";
	return $text;
}

function formatDate($date) {
	//default=english(MDY), DMY=latin, YMD=scientific
	global $wgNewszFormatDate;

	$m = substr($date,5,2);
	$d = substr($date,8,2);

	switch ($wgNewszFormatDate) {
		case 'DMY': return $d . "/" .  $m . "/" . substr($date,2,2);
		break;
		case 'YMD': return substr($date,0,4) . "/" .  $m . "/" . $d;
		break;
		default: return $m . "/" .  $d . "/" . substr($date,2,2);
	}
}

function fzFindFirstImage($page_content) {

	$pos = stripos($page_content, 'Image');

	if ($pos === false) return false;
	else {
		//check if open brackets [[ exists
		$open_brackets_pos = strpos($page_content, '[[', $pos-2);
		if ($open_brackets_pos === false) {
			$open_brackets_pos = strpos($page_content, '[[', $pos-6);
			if ($open_brackets_pos === false) return false;
		}
		if ($open_brackets_pos > $pos) return false;

		$end_string = strpos($page_content, ']]', $pos+6);
		$image_string=substr  ($page_content,$pos+6,$end_string-$pos-6);

		//sanitizer!
		$image_string = Sanitizer::encodeAttribute($image_string);

		trim($image_string);

		if (strlen($image_string) < 5) return false;

		$end_string = strpos($image_string, '|');
		if ($end_string === false) return $image_string;

		$image_string=substr  ($image_string,0,$end_string);
		trim($image_string);

		if ((strlen($image_string) < 5) || (strlen($image_string) > 127)) return false;
		else return $image_string;
	}
}

function fzGetAbstractByPeriod($page_content) {
//get text between first upper char and next point

	$pos = 0;

	while ($pos < strlen ($page_content)) {

		if (ctype_upper ($page_content [$pos])) {

			if (! $end_string = strpos ($page_content, ". ", $pos+1))
			$end_string = strpos ($page_content, ".\n", $pos+1);
			if ($end_string === false) return false;

			$abstract_string = substr ($page_content,$pos,$end_string-$pos+1);
			if (strlen ($abstract_string) > 0) {

				$mwSpecialChar = false;
				if (strpos ($abstract_string, '=', 0) != false) $mwSpecialChar = true;
				elseif (strpos ($abstract_string, '[', 0) != false) $mwSpecialChar = true;
				elseif (strpos ($abstract_string, ']', 0) != false) $mwSpecialChar = true;

				if (! $mwSpecialChar) {
					$abstract_string = Sanitizer::EncodeAttribute($abstract_string);
					return $abstract_string;
				}
			}
		}
		$pos++;
	}
	return false;
}

function fzGetArticleAbstract($page_content) {
//get text between hidden tags "NewsZ" and "EndZ"

	$pos = strpos($page_content, '<!--NewsZ');

	if ($pos === false) return false;
	else {
		$end_string = strpos($page_content, 'EndZ-->', $pos+9);
		if ($end_string === false) return false;

		$abstract_string = substr ($page_content,$pos+9,$end_string-$pos-9);

		$abstract_string = str_replace("<!--","",$abstract_string);
		$abstract_string = str_replace( "-->","",$abstract_string);

		trim($abstract_string);

		//16 chars minimum for abstract
		if (strlen($abstract_string) < 16) return false;

		//Sanitizer!
		$abstract_string = Sanitizer::safeEncodeAttribute($abstract_string);

		return $abstract_string;
	}
}

function fzURLencode($URLtoEncode) {
	global $wgScriptPath;

	//detect if PHP is as CGI or as module
	$index_path='/index.php/';
	if (substr(php_sapi_name(), 0, 3) == 'cgi') $index_path = '/index.php?title=';

	$urlEncoded = htmlspecialchars ($wgScriptPath . $index_path . $URLtoEncode);

	return $urlEncoded;
}

// code from NiceCategoryList
function getCategoryLinks($dbr, $title) {
	// Query the database.
	$res = $dbr->select(
		array('page', 'categorylinks'),
		array('page_title', 'page_namespace', 'cl_sortkey'),
		array('cl_from = page_id', 'cl_to' => $title->getDBKey()),
		'',
		array('ORDER BY' => 'cl_sortkey')
	);

	if ($res === false) return array();
 
	// Convert the results list into an array.
	$list = array();
	while ($x = $dbr->fetchObject($res)) $list[] = $x;

	// Free the results.
	$dbr->freeResult($res);
 
	return $list;
}

function fzGetArticles ($dbr,  &$res, $categories, $firstpage, $countpages) {

	list( $page, $categorylinks, $revision, $text) = $dbr->tableNamesN( 'page', 'categorylinks', 'revision', 'text');

	$sql = "SELECT cl_to, page_title, old_text, substr(cl_timestamp,1,10) as articleDate
		FROM  $categorylinks
		JOIN  $page ON page_id=cl_from
		JOIN  $revision ON rev_id=page_latest
		JOIN  $text ON old_id=rev_text_id
		WHERE ($categories) AND page_namespace = 0
		GROUP BY page_id
		ORDER BY cl_timestamp DESC
		LIMIT $firstpage , $countpages;";

	$res = $dbr->query( $sql );
}

function fzRenderNewsList(&$parser, $category, $firstpage, $countpages, $newscols, $divstyle) {
	global $wgNewsZthumbsize, $wgNewszNotSubCategory;
 
	$dbr = wfGetDB( DB_SLAVE );
 
	$firstpage = is_numeric($firstpage) ? $firstpage : 0;
	$countpages = is_numeric($countpages) ? $countpages : 5;
	$newscols = is_numeric($newscols) ? $newscols : 0;

	$newsDateAndPreview = false;
	//check if newscols in negative
	if ( $newscols < 0 ) {
		$newscols = abs ($newscols);
		if ( $newscols > 15 ) $newsDateAndPreview = true;
	}

	//Sanitizer!
	$divstyle=str_replace("<","",$divstyle);
	$divstyle=str_replace(">","",$divstyle);
	$category=str_replace(" ","_",$category);
	if ( $firstpage  > 10000 ) $firstpage=0;
	if ( $countpages > 200 )   $countpages=200;
	if ( $newscols   > 1000 )  $newscols=1000;
	if ( $category  == "" ) return '<table ' . $divstyle . '><tr><td>Category void!</td></tr></table>';

	$output='';

	$thumbsize='96x72px';
	if ($wgNewsZthumbsize != null) $thumbsize=$wgNewsZthumbsize;

	$newscounter = $newscols;

	$categories = "cl_to=" . $dbr->addQuotes($category);

	if ($wgNewszNotSubCategory == null) {
		//Add subcategories to 'cl_to=...' WHERE string
		$title = Title::newFromText($category);
		$links = getCategoryLinks($dbr, $title, 0);
 
		foreach ($links as $l) {
			// Make a Title for this item
			$title = Title::makeTitle($l->page_namespace, $l->page_title);
 
			if ($title->getNamespace() == NS_CATEGORY)
				$categories .= " OR cl_to=" . $dbr->addQuotes($l->page_title);
		}
	}

	fzGetArticles ($dbr, $res, $categories, $firstpage, $countpages);

	if ($dbr->numRows($res) <= 0)
		return '<table id="' . $category . '" ' . $divstyle . '><tr><td> No News in "' . $category . '"</td></tr></table>';

	$output .= '<table id="' . $category . '" ' . $divstyle . '>' ."\n";
 
	while ( $row = $dbr->fetchObject( $res ) ) {

		$article_cat = $row->cl_to;

		if (( $newscounter == $newscols ) || ( $newscols > 15 )) $output .= "<tr>\n";

		//find first image in article (if exists)
		$content=$row->old_text;
		$image_name=fzFindFirstImage($content);

		$timestamp=formatDate($row->articleDate);

		if ( ! $newsDateAndPreview ) {
			if ($image_name == false )
				$logo='[[Image:Logo-' . $article_cat . '.png|center|' . $thumbsize .'|link=' . $row->page_title . ']]';
			else
				$logo='[[Image:' . $image_name . '|center|' . $thumbsize . '|link=' . $row->page_title . ']]';

			$logo=$parser->replaceInternalLinks($logo);
		}

		if ($newscols > 15) {

			if ($newsz_tag_content=fzGetArticleAbstract($content))  $content = $newsz_tag_content;
			else $content=fzGetAbstractByPeriod($content);

			if ($content === false ) {
				$content=$row->page_title;
				$content=str_replace("_"," ",$content);
			}
			else   $content=fzShortenText($content, $newscols);

			if ( ! $newsDateAndPreview ) {
				$output .= '<td style="vertical-align:top">' . $logo .'</td>' .
				'<td align="left" valign="top" style="vertical-align:top; text-align:left"><a href="' . fzURLencode ($row->page_title) .
				'" target="_blank">' . $content . '</a>&nbsp;' . $timestamp . '</td>';
			}
			else {
				$output .= '<td style="vertical-align:top">' . $timestamp .'</td>' .
				'<td align="left" valign="top" style="vertical-align:top; text-align:left"><a href="' . fzURLencode ($row->page_title) .
				'" target="_blank">' . $content . '</a></td>';

			}
		}
		else {
			$content="==='''" . $row->page_title . "'''===";

			$content=$parser->doHeadings($content);
			$content=$parser->doAllQuotes($content);
			$content=str_replace("_"," ",$content);

			$content='<a href="' . fzURLencode ($row->page_title) .'" target="_blank">' . $content .'</a>';

			if ( $newscols == 0 ) {

				$content=$row->page_title;
				$content=str_replace("_"," ",$content);
 
				$output .= '<td align="left" valign="center" style="text-align:left">' . $timestamp;
				$output .= ' - <a href="' . fzURLencode ($row->page_title) .'" target="_blank">'
				. $content . '</a>';

			}
			else $output .= '<td style="text-align:center">' . $logo . ' ' . $content . ' ' . $timestamp . '</td>';
		}

		if ( $newscounter > 0 ) $newscounter=$newscounter-1;
 
		if (( $newscounter == 0 ) || ( $newscols > 15 )) {
			$newscounter=$newscols;
			$output .= "</tr>\n";
			}
	}
	$output .= "</table>";

	return array($output, 'noparse' => true, 'isHTML' => true, 'noargs' => true);

}
?>
