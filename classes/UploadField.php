<?php
/**
 * Curse Inc.
 * Upload Fields
 * UploadField Class
 *
 * @author		Alexia E. Smith
 * @copyright	(c) 2017 Curse Inc.
 * @license		GPL-2.0-or-later
 * @package		UploadFields
 * @link		http://www.curse.com/
 *
**/
namespace UploadFields;

use Title;
use Category;

class UploadField {
	/**
	 * Raw field information.
	 *
	 * @var		array
	 */
	protected $data = [];

	/**
	 * Field Types
	 *
	 * @var		array
	 */
	static public $types = [
		'select',
		'multiselect',
		'text',
		'textarea',
		'category'
	];

	/**
	 * Load a new instance from a database row.
	 *
	 * @access  public
	 * @param array $row Database Row
	 * @return mixed Object or false on error.
	 */
	public static function newFromRow($row) {
		list(, $type, $name) = explode('-', $row['page_title']);
		$type = strtolower($type);

		if (!in_array($type, self::$types) || empty($name)) {
			return false;
		}

		$field = new self;
		$field->setId($row['page_id']);
		$field->setLabel(ucfirst(mb_strtolower($name, "UTF-8")));
		$field->setType($type);
		$field->data['page'] = $row;

		return $field;
	}

	/**
	 * Get all fields.
	 *
	 * @access	public
	 * @return	array	Field objects
	 */
	public static function getAll() {
		$db = wfGetDB(DB_MASTER);

		$results = $db->select(
			['page'],
			['*'],
			[
				'page_namespace' => NS_MEDIAWIKI,
				"page_title REGEXP '^UploadField-.+-.+$'",
				'page_is_redirect' => 0
			],
			__METHOD__
		);

		$fields = [];
		while ($row = $results->fetchRow()) {
			$field = self::newFromRow($row);
			if ($field !== false) {
				$fields[$field->getId()] = $field;
			}
		}
		return $fields;
	}

	/**
	 * Set the field ID
	 *
	 * @access	public
	 * @param	int $id	field ID
	 * @return	boolean	True on success, false if the ID is already set.
	 */
	public function setId($id) {
		if (isset($this->data['ufid'])) {
			return false;
		}

		$this->data['ufid'] = intval($id);
		return true;
	}

	/**
	 * Return the database identification number for this field.
	 *
	 * @access	public
	 * @return	integer	Field ID
	 */
	public function getId() {
		return intval($this->data['ufid']);
	}

	/**
	 * Return the key for this field.
	 *
	 * @access	public
	 * @return	string	field Key
	 */
	public function getKey() {
		return $this->data['field_key'];
	}

	/**
	 * Set the key for this field.
	 * For usage by constructors only.
	 *
	 * @access	private
	 * @param	string	field Key
	 * @return	void
	 */
	private function setKey($key) {
		$this->data['field_key'] = $key;
	}

	/**
	 * Set the label.
	 *
	 * @access	public
	 * @param	string $label Label
	 * @return	boolean	Success
	 */
	public function setLabel($label) {
		if (empty($label)) {
			return false;
		}

		$this->data['label'] = substr($label, 0, 255);
		if (!isset($this->data['field_key']) || empty($this->data['field_key'])) {
			$this->data['field_key'] = $this->nameToKey($this->data['label']);
		}

		return true;
	}

	/**
	 * Return the label.
	 *
	 * @access	public
	 * @return	string	Label
	 */
	public function getLabel() {
		return $this->data['label'];
	}

	/**
	 * Return the field type.
	 *
	 * @access	public
	 * @return	string	Type
	 */
	public function getType() {
		return $this->data['type'];
	}

	/**
	 * Set the form ordering.
	 *
	 * @access	public
	 * @param	int $type Order
	 * @return	boolean	Success
	 */
	public function setType($type) {
		if (in_array($type, self::$types)) {
			$this->data['type'] = $type;
			return true;
		}
		return false;
	}

	/**
	 * Return the HTMLFormField HTML.
	 *
	 * @access	public
	 * @param	HTMLForm $htmlForm
	 * @return	string	HTML
	 */
	public function getHtml(HTMLForm $htmlForm) {
		$parameters = $this->getDescriptor($htmlForm);
		$parameters['parent'] = $htmlForm;

		$field = new $parameters['class']($parameters);

		return $field->getTableRow($parameters['value']);
	}

	/**
	 * Get the field descriptor.
	 *
	 * @access	public
	 * @return	array	Descriptor array suitable for passing into a new HTML*Field class.
	 */
	public function getDescriptor() {
		$parameters = [
			'name'		=> $this->getKey(),
			'fieldname'	=> $this->getKey(),
			'label'		=> $this->getLabel() . ':',
			'section'	=> 'description'
		];

		$value = '';
		switch ($this->data['type']) {
			case 'select':
				$parameters['options'] = $this->parseOptions();
				$parameters['class'] = 'HTMLSelectField';
				break;
			case 'multiselect':
				$parameters['options'] = $this->parseOptions();
				$parameters['class'] = 'HTMLMultiSelectField';
				break;
			case 'text':
				$parameters['default'] = wfMessage($this->data['page']['page_title'])->plain();
				$parameters['class'] = 'HTMLTextField';
				break;
			case 'textarea':
				$parameters['default'] = wfMessage($this->data['page']['page_title'])->plain();
				$parameters['rows'] = 5;
				$parameters['class'] = 'HTMLTextAreaField';
				break;
			case 'category':
				$db = wfGetDB(DB_MASTER);

				$results = $db->select(
					['category'],
					['*'],
					null,
					__METHOD__
				);

				$options = [];
				while ($row = $results->fetchObject()) {
					$category = Category::newFromRow($row);
					$options[$category->getName()] = $category->getName();
				}
				$parameters['options'] = $options;
				$parameters['class'] = 'HTMLMultiSelectField';
				$value = [];
				break;
		}
		$parameters['value'] = $value;

		return $parameters;
	}

	/**
	 * Get the field descriptor.
	 *
	 * @access	public
	 * @param	mixed $input Result of the form input.
	 * @return	text	Text suitable to be used as wiki text based on the passed back input.
	 */
	public function getWikiText($input) {
		$text = '';
		switch ($this->data['type']) {
			case 'select':
			case 'multiselect':
			case 'text':
			case 'textarea':
				// $text = $this->getKey().'='.str_replace('|', '{{!}}', $input);
				$text = $this->getKey() . '=' . $input;
				break;
			case 'category':
				$input = (array)$input;

				foreach ($input as $key => $category) {
					$title = Title::makeTitleSafe(NS_CATEGORY, $category);
					if (!is_object($title)) {
						unset($input[$key]);
						continue;
					}

					$input[$key] = $title->getDBkey();
					$titles[$key] = $title->getPrefixedText();
				}

				$db = wfGetDB(DB_MASTER);

				$results = $db->select(
					['category'],
					['*'],
					['cat_title' => $input],
					__METHOD__
				);

				$found = [];
				while ($row = $results->fetchRow()) {
					$found[] = $row['cat_title'];
				}

				$found = array_intersect($input, $found);
				$titles = array_intersect_key($titles, $found);
				if (!empty($titles)) {
					$text = implode(",", $titles);
				}

				break;
		}

		return $text;
	}

	/**
	 * Turns human friendly names into computer friendly keys.
	 *
	 * @access	private
	 * @param	string	Name
	 * @return	string	Key
	 */
	private function nameToKey($name) {
		return trim(preg_replace("#-{2,}#", "-", preg_replace("#[\W]#i", "-", trim(mb_strtolower($name, "UTF-8")))), '-');
	}

	/**
	 * Parses multilevel options from a message into descriptor options.
	 *
	 * @access	private
	 * @return	array	Options
	 */
	private function parseOptions() {
		$options = [];

		$text = wfMessage($this->data['page']['page_title'])->plain();
		if (!empty($text)) {
			$lines = explode("\n", $text);
			$options = $this->parseDepth($lines);
		}

		return $options;
	}

	/**
	 * Parses single options depth.
	 *
	 * @access	private
	 * @param	array	Array to add array into.
	 * @param	integer	[Optional] Current Depth
	 * @return	array	Options
	 */
	private function parseDepth(&$lines, $depth = 1) {
		$options = [];
		while ($line = $this->eachLine($lines)) {
			// Ignore blank or invalid lines.
			if (substr($line, 0, 1) === '*') {
				$currentDepth = $this->checkDepth($line);
				if ($currentDepth > 2) {
					// Option groups do not support more than one depth.
					$line = substr($line, $currentDepth - 2);
					$currentDepth = 2;
				}

				if ($currentDepth > $depth) {
					prev($lines);
					end($options);
					$options[key($options)] = $this->parseDepth($lines, $currentDepth);
					continue;
				} elseif ($currentDepth < $depth) {
					prev($lines);
					break;
				}

				$label = '';
				$value = '';
				if (strpos($line, '|') !== false) {
					list($value, $label) = explode('|', substr($line, $depth), 2);
				} else {
					$value = substr($line, $depth);
				}
				$label = (string)$label;
				$value = (string)$value;
				if (empty($label) && !empty($value)) {
					$label = $value;
				}
				if (empty($label)) {
					// No empty labels allowed.
					continue;
				}
				$options[$label] = $value;
			}
		}
		return $options;
	}

	/**
	 * Psuedo each() functionality for the deprecated each() function.
	 *
	 * @access	private
	 * @param	array	Array of lines to advance.
	 * @return	string	Line
	 */
	private function eachLine(&$lines) {
		$value = current($lines);
		next($lines);
		return $value;
	}

	/**
	 * Get the depth of the options for this single line.
	 *
	 * @access	private
	 * @param	string	Line
	 * @return	integer	Line Depth
	 */
	private function checkDepth($line) {
		$i = 0;
		while (substr($line, $i, 1) === '*') {
			$i++;
			if ($i >= 10) {
				break;
			}
			continue;
		}
		return $i;
	}
}
