<?php

namespace EWZ\SymfonyAdminBundle\Form;

use EWZ\SymfonyAdminBundle\Entity\Filter;
use EWZ\SymfonyAdminBundle\Entity\Report;
use EWZ\SymfonyAdminBundle\Form\DataTransformer\ObjectToIdTransformer;
use EWZ\SymfonyAdminBundle\Form\DataTransformer\StringToJsonTransformer;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilterFormType extends AbstractType
{
    /** @var RegistryInterface */
    protected $registry;

    /**
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', null, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'form.filter.name',
                ],
            ])
            ->add('section', HiddenType::class)
            ->add('report', HiddenType::class)
            ->add('params', HiddenType::class)
        ;

        $builder->get('report')->addModelTransformer(new ObjectToIdTransformer($this->registry, Report::class));
        $builder->get('params')->addModelTransformer(new StringToJsonTransformer());
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Filter::class,
        ]);
    }
}
