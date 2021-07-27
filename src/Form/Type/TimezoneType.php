<?php

namespace EWZ\SymfonyAdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimezoneType extends AbstractType
{
    /** @var array */
    private static $timezones;

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'choices' => self::getTimezones(),
        ]);
    }

    /**
     * Returns the timezone choices.
     *
     * The choices are generated from the ICU function \DateTimeZone::listIdentifiers().
     * They are cached during a single request, so multiple timezone fields on the same
     * page don't lead to unnecessary overhead.
     *
     * @return array
     */
    public static function getTimezones(): array
    {
        if (null === static::$timezones) {
            static::$timezones = [];

            foreach (\DateTimeZone::listIdentifiers() as $timezone) {
                $parts = explode('/', $timezone);

                $dateTimeZone = new \DateTimeZone($timezone);
                $dateTime = new \DateTime('now', $dateTimeZone);
                $offset = sprintf('GMT%s', $dateTime->format('P'));

                if (\count($parts) > 1) {
                    $name = sprintf('(%s) %s', $offset, $timezone);
                } else {
                    $name = sprintf('(%s) %s', $offset, 'Other');
                }

                static::$timezones[str_replace('_', ' ', $name)] = $timezone;
            }
        }

        return static::$timezones;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return ChoiceType::class;
    }
}
