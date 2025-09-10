<?php

namespace App\Presentation\Form;

use App\Domain\Billing\Entity\Plan;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
        
        // Create choices array for ChoiceType - use plan ID as value instead of objects
        $planChoices = [];
        foreach ($activePlans as $plan) {
            // Handle both legacy Plan entities and DDD domain Plan objects
            if (method_exists($plan, 'getPlanName') && method_exists($plan, 'getAmount') && method_exists($plan, 'getFrequency')) {
                // Legacy Plan entity
                $label = sprintf('%s - $%s %s', 
                    $plan->getPlanName(), 
                    number_format($plan->getAmount(), 2), 
                    $plan->getFrequency()
                );
                $planChoices[$label] = $plan->getId(); // Use database ID for legacy plans
            } else {
                // DDD Domain Plan object
                $label = sprintf('%s - $%s %s', 
                    $plan->getName(), 
                    number_format($plan->getAmount()->getAmount(), 2), 
                    $plan->getBillingCycle()->getFrequency()
                );
                $planChoices[$label] = $plan->getPlanId()->getValue(); // Use plan ID for domain plans
            }
        }
        
        $builder
            ->add('subscribeAndCheckout', CheckboxType::class, [
                'label' => 'Subscribe and Checkout (for recurring billing)',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input',
                    'id' => 'checkout_subscribeAndCheckout',
                ],
                'label_attr' => [
                    'class' => 'form-check-label',
                ],
            ])
            ->add('plan_id', ChoiceType::class, [
                'choices' => $planChoices,
                'placeholder' => 'Select a plan...',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'checkout_plan_id',
                ],
                'label' => 'Subscription Plan',
                'label_attr' => [
                    'class' => 'form-label',
                ],
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Amount ($)',
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0.01',
                    'id' => 'checkout_amount',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Positive(),
                ],
                'scale' => 2,
            ])
            ->add('currency', TextType::class, [
                'data' => 'USD',
                'attr' => [
                    'readonly' => true,
                    'class' => 'form-control',
                ],
            ])
            // Billing information
            ->add('billingFirstName', TextType::class, [
                'label' => 'First Name',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 50]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('billingLastName', TextType::class, [
                'label' => 'Last Name',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 50]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('billingEmail', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('billingAddress1', TextType::class, [
                'label' => 'Address Line 1',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 100]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('billingAddress2', TextType::class, [
                'label' => 'Address Line 2',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 100]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('billingCity', TextType::class, [
                'label' => 'City',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 50]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('billingState', TextType::class, [
                'label' => 'State/Province',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 50]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('billingPostal', TextType::class, [
                'label' => 'ZIP/Postal Code',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 20]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('billingCountry', TextType::class, [
                'label' => 'Country',
                'data' => 'USA',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 50]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('billingPhone', TextType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'constraints' => [
                    new Regex([
                        'pattern' => '/^[\d\s\-\+\(\)]+$/',
                        'message' => 'Please enter a valid phone number',
                    ]),
                    new Length(['max' => 20]),
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('submitPayment', SubmitType::class, [
                'label' => 'Proceed to Payment',
                'attr' => ['class' => 'btn btn-primary btn-lg w-100'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'active_plans' => [],
        ]);
    }
}
