<?php

namespace EWZ\SymfonyAdminBundle\Annotation;

use Doctrine\Common\Annotations\Annotation;
use EWZ\SymfonyAdminBundle\Exception\AnnotationException;

/**
 * @Annotation
 */
class ConfigField
{
    /** @var array */
    public $defaultValues = [];

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        if (isset($data['defaultValues'])) {
            $this->defaultValues = $data['defaultValues'];
        }

        if (!is_array($this->defaultValues)) {
            throw new AnnotationException(
                sprintf(
                    'Annotation "Config" parameter "defaultValues" expect "array" but "%s" given',
                    gettype($this->defaultValues)
                )
            );
        }
    }
}
