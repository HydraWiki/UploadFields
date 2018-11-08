<?php
/**
 * Curse Inc.
 * Upload Fields
 * Add custom fields to file uploads to be automatically added to templates on the file pages.
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		GPL-2.0-or-later
 * @package		UploadFields
 * @link		http://www.curse.com/
 *
**/
namespace UploadFields;

use ContentHandler;
use HTMLForm;
use RequestContext;
use Revision;
use SpecialUpload;
use WikiPage;

class Hooks {
	/**
	 * Add categories to finished upload.
	 *
	 * @access	public
	 * @param	SpecialUpload $specialUpload Upload form special page.
	 * @return	boolean	true
	 */
	public static function onSpecialUploadComplete(SpecialUpload $specialUpload) {
		if ($specialUpload->mForReUpload) {
			return true;
		}

		$wikiPage = WikiPage::factory($specialUpload->mLocalFile->getTitle());
		$content = $wikiPage->getContent(Revision::RAW);
		$currentText = '';
		if ($content !== null) {
			$currentText = $content->getNativeData();
		}

		if (strpos($currentText, "{{FileInfo") !== false) {
			// Already added.
			return true;
		}

		$fields = UploadField::getAll();
		if (!empty($fields)) {
			$fileInfo = [];
			$request = $specialUpload->getRequest();

			// $fileInfo[] = "summary=".str_replace('|', '{{!}}', $request->getText("wpUploadDescription"));
			$fileInfo[] = "summary=" . $request->getText("wpUploadDescription");

			foreach ($fields as $field) {
				$value = $request->getVal($field->getKey());
				if ($value !== null) {
					$fileInfo[] = $field->getWikiText($value);
				}
			}

			if (!empty($fileInfo)) {
				$fileInfoText = "{{FileInfo\n|" . implode("\n|", $fileInfo) . "\n}}";

				$currentText = (empty($currentText) ? $fileInfoText : $currentText . "\n\n" . $fileInfoText);
				$newContent = ContentHandler::makeContent($currentText, $specialUpload->mLocalFile->getTitle());
				$wikiPage->doEditContent(
					$newContent,
					'',
					EDIT_AUTOSUMMARY | EDIT_SUPPRESS_RC
				);
			}
		}

		return true;
	}

	/**
	 * Modify upload form fields.
	 *
	 * @access	public
	 * @param	array	&$descriptor Descriptor
	 * @return	boolean	True
	 */
	public static function onUploadFormInitDescriptor(&$descriptor) {
		if (isset($descriptor['ForReUpload'])) {
			// Do not show these for reupload.
			return true;
		}

		$fields = UploadField::getAll();

		$context = RequestContext::getMain();
		$htmlForm = new HTMLForm([], $context);

		if (!empty($fields)) {
			foreach ($fields as $field) {
				$descriptor[$field->getLabel()] = $field->getDescriptor($htmlForm);
			}
		}

		return true;
	}
}
