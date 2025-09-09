<?php

namespace App\Presentation\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class PlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('planName', TextType::class, [
                'label' => 'Plan Name',
                'constraints' => [
                    new NotBlank(['message' => 'Plan name is required']),
                    new Length(['max' => 100, 'maxMessage' => 'Plan name cannot exceed 100 characters']),
                ],
                'attr' => [
                    'placeholder' => 'e.g., Premium Monthly Plan',
                    'class' => 'form-control',
                ],
                'help' => 'Give this plan a descriptive name',
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Amount',
                'scale' => 2,
                'html5' => true,
                'data' => 10.00,
                'constraints' => [
                    new NotBlank(['message' => 'Amount is required']),
                    new Positive(['message' => 'Amount must be greater than 0']),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0.01',
                ],
            ])
            ->add('frequency', ChoiceType::class, [
                'label' => 'Billing Frequency',
                'choices' => [
                    'Weekly (every 7 days)' => 'weekly',
                    'Monthly (every 30 days)' => 'monthly',
                    'Yearly (every 365 days)' => 'yearly',
                ],
                'data' => 'monthly',
                'constraints' => [
                    new NotBlank(['message' => 'Frequency is required']),
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Create Plan',
                'attr' => [
                    'class' => 'btn btn-primary btn-lg',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
        ]);
    }
}
