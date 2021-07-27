<?php

namespace EWZ\SymfonyAdminBundle\Form;

use Doctrine\Persistence\ManagerRegistry;
use EWZ\SymfonyAdminBundle\Entity\Filter;
use EWZ\SymfonyAdminBundle\Form\DataTransformer\ObjectToIdTransformer;
use EWZ\SymfonyAdminBundle\Form\DataTransformer\StringToJsonTransformer;
use EWZ\SymfonyAdminBundle\Repository\ReportRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilterFormType extends AbstractType
{
    /** @var ManagerRegistry */
    protected $managerRegistry;

    /** @var ReportRepository */
    protected $reportRepository;

    /**
     * @param ManagerRegistry  $managerRegistry
     * @param ReportRepository $reportRepository
     */
    public function __construct(ManagerRegistry $managerRegistry, ReportRepository $reportRepository)
    {
        $this->managerRegistry = $managerRegistry;
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

        $builder->get('report')->addModelTransformer(new ObjectToIdTransformer($this->managerRegistry, $this->reportRepository->getClass()));
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
