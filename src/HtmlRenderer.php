<?php
declare(strict_types=1);

namespace Meraki\Schema;

use Meraki\Schema\SchemaFacade;
use Meraki\Schema\Field;

/**
 * Renders a schema as an HTML form.
 *
 * IT SHOULD NOT BE USED IN PRODUCTION CODE OR RELIED UPON BY OTHER LIBRARIES.
 *
 * @deprecated Authors should create their own schema renderers.
 */
class HtmlRenderer
{
	private const DEFAULT_UI_OPTIONS = [
		Field\Address::class => [
			'label' => 'Address',
			'renderer' => 'text',
		],
		Field\Boolean::class => [
			'renderer' => 'checkbox',
		],
		Field\CreditCard::class => [
			'renderer' => 'text',
		],
		Field\Date::class => [
			'renderer' => 'date',
		],
		Field\DateTime::class => [
			'renderer' => 'datetime-local',
		],
		Field\Duration::class => [
			'renderer' => 'text',
		],
		Field\EmailAddress::class => [
			'label' => 'Email Address',
			'renderer' => 'email',
		],
		Field\Enum::class => [
			'renderer' => 'radio',
		],
		Field\File::class => [
			'renderer' => 'file',
		],
		Field\Money::class => [
			'renderer' => 'number',
		],
		Field\Name::class => [
			'label' => 'Full Name',
			'renderer' => 'text',
		],
		Field\Number::class => [
			'renderer' => 'number',
		],
		Field\Passphrase::class => [
			'label' => 'Passphrase',
			'renderer' => 'password',
		],
		Field\Password::class => [
			'label' => 'Password',
			'renderer' => 'password',
		],
		Field\PhoneNumber::class => [
			'label' => 'Phone Number',
			'renderer' => 'tel',
		],
		Field\Text::class => [
			'multiline' => false,
			'renderer' => 'text',
		],
		Field\Time::class => [
			'renderer' => 'time',
		],
		Field\Url::class => [
			'renderer' => 'url',
		],
		Field\Uuid::class => [
			'renderer' => 'text',
		],
	];

	private const GLOBAL_DEFAULT_UI_OPTIONS = [
		'readonly' => false,
		'disabled' => false,
		'hidden' => false,
		'autofocus' => false,
	];

	private array $fieldRenderers = [];

	/**
	 * @param array[] $ui Options for the HTML serializer. Each key must map to the field name.
	 */
	public function __construct(private array $ui = [])
	{
		$this->registerDefaultFieldRenderers();
	}

	public function render(SchemaFacade $schema): string
	{
		$uiOptions = (object)$this->ui;
		$html = sprintf('<form id="%s"', $schema->name);
		$html .= ' action="'. ($uiOptions->action ?? '') .'" method="'. ($uiOptions->method ?? 'post') .'">';

		foreach ($schema->fields as $field) {
			$html .= $this->renderField($field);
		}

		$html .= '<button type="submit">Submit</button>';
		$html .= '</form>';

		return $html;
	}

	/**
	 * Register a field serializer for a specific field type.
	 *
	 * Overwrites any existing field renderer for the given field type.
	 */
	public function registerFieldRenderer(string $fqcn, callable $serializer): void
	{
		$this->fieldRenderers[$fqcn] = $serializer;
	}

	private function registerDefaultFieldRenderers(): void
	{
		$this->registerFieldRenderer(Field\Address::class, $this->renderAddressField(...));
		$this->registerFieldRenderer(Field\Boolean::class, $this->renderBooleanField(...));
		$this->registerFieldRenderer(Field\CreditCard::class, $this->renderCreditCardField(...));
		$this->registerFieldRenderer(Field\Date::class, $this->renderDateField(...));
		$this->registerFieldRenderer(Field\DateTime::class, $this->renderDateTimeField(...));
		$this->registerFieldRenderer(Field\Duration::class, $this->renderDurationField(...));
		$this->registerFieldRenderer(Field\EmailAddress::class, $this->renderEmailAddressField(...));
		$this->registerFieldRenderer(Field\Enum::class, $this->renderEnumField(...));
		$this->registerFieldRenderer(Field\File::class, $this->renderFileField(...));
		$this->registerFieldRenderer(Field\Money::class, $this->renderMoneyField(...));
		$this->registerFieldRenderer(Field\Name::class, $this->renderNameField(...));
		$this->registerFieldRenderer(Field\Number::class, $this->renderNumberField(...));
		$this->registerFieldRenderer(Field\Passphrase::class, $this->renderPassphraseField(...));
		$this->registerFieldRenderer(Field\Password::class, $this->renderPasswordField(...));
		$this->registerFieldRenderer(Field\PhoneNumber::class, $this->renderPhoneNumberField(...));
		$this->registerFieldRenderer(Field\Text::class, $this->renderTextField(...));
		$this->registerFieldRenderer(Field\Time::class, $this->renderTimeField(...));
		$this->registerFieldRenderer(Field\Url::class, $this->renderUrlField(...));
		$this->registerFieldRenderer(Field\Uuid::class, $this->renderUuidField(...));
	}

	private function renderField(Field $field): string
	{
		$renderer = $this->fieldRenderers[$field::class] ?? null;

		if ($renderer === null) {
			throw new \RuntimeException('No renderer registered for field type: ' . $field::class);
		}

		$globalUiOptions = self::GLOBAL_DEFAULT_UI_OPTIONS;
		$uiOptionsForFieldType = self::DEFAULT_UI_OPTIONS[$field::class] ?? [];
		$uiOptions = array_merge($globalUiOptions, $uiOptionsForFieldType, $this->ui[$field->name->value] ?? []);

		return $renderer($field, (object)$uiOptions);
	}

	private function renderAddressField(Field\Address $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="text" id="%s" name="%s" value="%s"', $id, $field->name->value, $field->value?->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('address', $html);
	}

	private function renderBooleanField(Field\Boolean $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="checkbox" id="%s" name="%s" autocomplete="off"', $id, $field->name->value);
		$html .= $field->value ? ' checked' : '';
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('boolean', $html);
	}

	private function renderCreditCardField(Field\CreditCard $field, object $uiOptions): string
	{
	}

	private function renderDateField(Field\Date $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="date" id="%s" name="%s" value="%s"', $id, $field->name->value, $field->value?->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('date', $html);
	}

	private function renderDateTimeField(Field\DateTime $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="datetime-local" id="%s" name="%s" value="%s"', $id, $field->name->value, $field->value?->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('datetime', $html);
	}

	private function renderDurationField(Field\Duration $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="text" id="%s" name="%s" value="%s"', $id, $field->name->value, $field->value?->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('duration', $html);
	}

	private function renderEmailAddressField(Field\EmailAddress $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="email" id="%s" name="%s" value="%s"', $id, $field->name->value, $field->value?->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('email', $html);
	}

	private function renderEnumField(Field\Enum $field, object $uiOptions): string
	{
		$html = '';

		if ($uiOptions->renderer === 'dropdown') {
			$id = $this->generateDeterministicId($field->name->value);
			$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
			$html .= sprintf('<select id="%s" name="%s"', $id, $field->name->value);
			$html .= $this->addGlobalAttributes($html, $field, $uiOptions);
			$html .= '>';

			foreach ($field->one_of as $option) {
				$optionUiOptions = $uiOptions->options[$option] ?? [];
				$label = $optionUiOptions->label ?? $option;
				$html .= sprintf('<option value="%s">%s</option>', $option, $label);
			}

			$html .= '</select>';
		// Default to radio buttons
		} else {
			$html = '<fieldset>';
			$html .= sprintf('<legend>%s</legend>', $uiOptions->label ?? $this->generateLabelFromName($field->name->value));

			foreach ($field->one_of as $option) {
				$optionUiOptions = (object)($uiOptions->options[$option] ?? []);
				$label = $optionUiOptions->label ?? $option;
				$id = $optionUiOptions->id ?? $this->generateDeterministicId($option);
				$html .= sprintf('<label for="%s">%s</label>', $id, $label);
				$html .= sprintf('<input type="radio" id="%s" name="%s" value="%s">', $id, $field->name->value, $option);
			}

			$html .= '</fieldset>';
		}

		return $this->wrapWithFieldElement('enum', $html);
	}

	private function renderFileField(Field\File $field, object $uiOptions): string
	{
	}

	private function renderMoneyField(Field\Money $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="%s" id="%s" name="%s" value="%s"', $uiOptions->renderer, $id, $field->name->value, $field->value?->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('money', $html);
	}

	private function renderNameField(Field\Name $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="%s" id="%s" name="%s" value="%s"', $uiOptions->renderer, $id, $field->name->value, $field->value?->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('name', $html);
	}

	private function renderNumberField(Field\Number $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="%s" id="%s" name="%s" value="%s"', $uiOptions->renderer, $id, $field->name->value, $field->value?->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('number', $html);
	}

	private function renderPassphraseField(Field\Passphrase $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="%s" id="%s" name="%s" value="%s"', $uiOptions->renderer, $id, $field->name->value, $field->value?->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('passphrase', $html);
	}

	private function renderPasswordField(Field\Password $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="%s" id="%s" name="%s" value="%s"', $uiOptions->renderer, $id, $field->name->value, $field->value?->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('password', $html);
	}

	private function renderPhoneNumberField(Field\PhoneNumber $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="%s" id="%s" name="%s" value="%s"', $uiOptions->renderer, $id, $field->name->value, $field->value?->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('phone-number', $html);
	}

	private function renderTextField(Field\Text $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));

		if (($uiOptions->multiline ?? false) || $field->renderer === 'textarea') {
			$html .= '<textarea';
		} else {
			$html .= '<input type="text"';
		}

		$html .= sprintf(' id="%s" name="%s" value="%s"', $id, $field->name->value, $field->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('text', $html);
	}

	private function renderTimeField(Field\Time $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="%s" id="%s" name="%s" value="%s"', $field->renderer, $id, $field->name->value, $field->value?->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('time', $html);
	}

	private function renderUrlField(Field\Url $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="%s" id="%s" name="%s" value="%s"', $field->renderer, $id, $field->name->value, $field->value?->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('url', $html);
	}

	private function renderUuidField(Field\Uuid $field, object $uiOptions): string
	{
		$id = $uiOptions->id ?? $this->generateDeterministicId($field->name->value);
		$html = sprintf('<label for="%s">%s</label>', $id, $uiOptions->label ?? $this->generateLabelFromName($field->name->value));
		$html .= sprintf('<input type="%s" id="%s" name="%s" value="%s"', $field->renderer, $id, $field->name->value, $field->value);
		$html = $this->addGlobalAttributes($html, $field, $uiOptions);
		$html .= '>';

		return $this->wrapWithFieldElement('uuid', $html);
	}

	private function addGlobalAttributes(string $html, Field $field, object $uiOptions): string
	{
		$html .= $field->isRequired() ? ' required' : '';
		$html .= $uiOptions->readonly ? ' readonly' : '';
		$html .= $uiOptions->disabled ? ' disabled' : '';
		$html .= $uiOptions->hidden ? ' hidden' : '';
		$html .= $uiOptions->autofocus ? ' autofocus' : '';

		return $html;
	}

	private function wrapWithFieldElement(string $type, string $innerHtml): string
	{
		return "<div class=\"field\" data-type=\"{$type}\">{$innerHtml}</div>";
	}

	/**
	 * Generates a label from a field name.
	 */
	private function generateLabelFromName(string $name): string
	{
		return ucfirst(str_replace('_', ' ', $name));
	}

	/**
	 * Generates a deterministic unique ID for a field.
	 */
	private function generateDeterministicId(string $name): string
	{
		return 'input-' . hash('sha256', $name);
	}
}
