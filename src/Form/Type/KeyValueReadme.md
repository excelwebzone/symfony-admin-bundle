KeyValueType
============

A form type for managing key-value pairs.

Usage
-----

To add to your form, use the `KeyValueType` type:

```php
use EWZ\SymfonyAdminBundle\Form\Type\KeyValueType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

$builder->add('parameters', KeyValueType::class, ['value_type' => TextType::class]);

// or

$formFactory->create(KeyValueType::class, $data, ['value_type' => TextType::class]);
```

The type extends the collection type, so for rendering it in the browser, the same logic is used. See the
[Symfony docs on collection types](http://symfony.com/doc/current/cookbook/form/form_collections.html) for
an example on how to render it client side.

The type adds four options to the collection type options, of which one is required:

  * `value_type` (required) defines which form type to use to render the value field
  * `value_options` optional options to the child defined in `value_type`
  * `key_type` defines which form type to use to render the key field (default is a `text` field)
  * `key_options` optional options to the child defined in `key_type`
  * `allowed_keys` if this option is provided, the key field (which is usually a simple text field) will change to a `choice` field, and allow only those values you supplied in the this option.

Besides that, this type overrides some defaults of the collection type and it's recommended you don't change them:
`type` is set to `KeyValueRowType::class` and `allow_add` and `allow_delete` are always `true`.
