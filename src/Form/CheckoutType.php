<?php

namespace App\Form;

use App\Entity\Plan;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Regex;

class CheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $activePlans = $options['active_plans'] ?? [];
        
        $builder
            ->add('subscribeAndCheckout', CheckboxType::class, [
                'label' => 'Subscribe and Checkout (for recurring billing)',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'id' => 'subscribe-checkbox',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
            ])
            ->add('plan', EntityType::class, [
                'class' => Plan::class,
                'choices' => $activePlans,
                'choice_label' => function (Plan $plan) {
                    return sprintf('%s - $%s %s', $plan->getPlanName(), number_format($plan->getAmount(), 2), $plan->getFrequency());
                },
                'placeholder' => 'Select a plan...',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'plan-select',
                ],
                'label' => 'Select Plan',
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Amount',
                'scale' => 2,
                'html5' => true,
                'required' => false, // Will be conditionally validated via JavaScript
                'constraints' => [
                    new Positive(),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'amount-input',
                ],
            ])
            ->add('currency', TextType::class, [
                'label' => 'Currency (e.g., USD)',
                'data' => 'USD',
                'attr' => ['readonly' => true, 'class' => 'form-control'],
            ])
            // Billing Information
            ->add('billingFirstName', TextType::class, [
                'label' => 'First Name',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 50]),
                ],
            ])
            ->add('billingLastName', TextType::class, [
                'label' => 'Last Name',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 50]),
                ],
            ])
            ->add('billingAddress1', TextType::class, [
                'label' => 'Address',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 100]),
                ],
            ])
            ->add('billingAddress2', TextType::class, [
                'label' => 'Address 2',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 100]),
                ],
            ])
            ->add('billingCity', TextType::class, [
                'label' => 'City',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 50]),
                ],
            ])
            ->add('billingState', TextType::class, [
                'label' => 'State/Province',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 50]),
                ],
            ])
            ->add('billingPostal', TextType::class, [
                'label' => 'Zip/Postal Code',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 20]),
                ],
            ])
            ->add('billingCountry', TextType::class, [
                'label' => 'Country',
                'data' => 'US',
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => 2, 'max' => 2]),
                ],
            ])
            ->add('billingEmail', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'constraints' => [
                    new Email(),
                ],
            ])
            ->add('billingPhone', TextType::class, [
                'label' => 'Phone',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 20]),
                ],
            ])
            ->add('submitPayment', SubmitType::class, [
                'label' => 'Process Payment',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'active_plans' => [],
        ]);
        
        $resolver->setAllowedTypes('active_plans', 'array');
    }
}
