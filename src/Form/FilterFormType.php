<?php

namespace EWZ\SymfonyAdminBundle\Form;

use EWZ\SymfonyAdminBundle\Entity\Filter;
use EWZ\SymfonyAdminBundle\Form\DataTransformer\ObjectToIdTransformer;
use EWZ\SymfonyAdminBundle\Form\DataTransformer\StringToJsonTransformer;
use EWZ\SymfonyAdminBundle\Repository\ReportRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilterFormType extends AbstractType
{
    /** @var RegistryInterface */
    protected $registry;

    /** @var ReportRepository */
    protected $reportRepository;

    /**
     * @param RegistryInterface $registry
     * @param ReportRepository  $reportRepository
     */
    public function __construct(RegistryInterface $registry, ReportRepository $reportRepository)
    {
        $this->registry = $registry;
        $this->reportRepository = $reportRepository;
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

        $builder->get('report')->addModelTransformer(new ObjectToIdTransformer($this->registry, $this->reportRepository->getClass()));
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
