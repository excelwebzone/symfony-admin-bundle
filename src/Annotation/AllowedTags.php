<?php

namespace EWZ\SymfonyAdminBundle\Annotation;

use Doctrine\Common\Annotations\Annotation;
use EWZ\SymfonyAdminBundle\Exception\AnnotationException;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
class AllowedTags
{
    /** @var array */
    public $tags = [];

    /**
     * @param array $data
     *
     * @throws AnnotationException
     * @throws \InvalidArgumentException
     */
    public function __construct(array $data)
    {
        if (isset($data['tags'])) {
            $this->tags = $data['tags'];
        }

        if (!is_array($this->tags)) {
            throw new AnnotationException(
                sprintf(
                    'Annotation "Config" parameter "tags" expect "array" but "%s" given',
                    gettype($this->tags)
                )
            );
        }

        $tagPattern = '/^<[a-z]+>$/';

        foreach ($this->tags as $tag) {
            if (false === preg_match($tagPattern, $tag)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'The "%s" tag does not look like a valid tag (its must match the following regexp: "%s")',
                        $tag,
                        $tagPattern
                    )
                );
            }
        }
    }

    /**
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }
}
