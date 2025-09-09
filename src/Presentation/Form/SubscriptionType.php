<?php

namespace App\Presentation\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class SubscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $activePlans = $options['active_plans'] ?? [];

        $builder
            ->add('plan_id', ChoiceType::class, [
                'choices' => $this->buildPlanChoices($activePlans),
                'choice_label' => function ($choice, $key, $value) {
                    return $key; // The key is already formatted
                },
                'label' => 'Select Plan',
                'placeholder' => 'Choose a subscription plan...',
                'constraints' => [
                    new NotBlank(['message' => 'Please select a plan']),
                ],
                'help' => 'Choose from available subscription plans (only active plans shown)',
            ])
            ->add('start_date', DateType::class, [
                'label' => 'Start Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'data' => new \DateTimeImmutable('+1 day'),
                'constraints' => [
                    new NotBlank(),
                ],
                'help' => 'When to start the subscription',
            ])
            // Customer Information
            ->add('customer_email', EmailType::class, [
                'label' => 'Customer Email',
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                ],
            ])
            // Billing Information
            ->add('billing_first_name', TextType::class, [
                'label' => 'First Name',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 50]),
                ],
            ])
            ->add('billing_last_name', TextType::class, [
                'label' => 'Last Name',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 50]),
                ],
            ])
            ->add('billing_address1', TextType::class, [
                'label' => 'Address',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 100]),
                ],
            ])
            ->add('billing_address2', TextType::class, [
                'label' => 'Address 2',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 100]),
                ],
            ])
            ->add('billing_city', TextType::class, [
                'label' => 'City',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 50]),
                ],
            ])
            ->add('billing_state', TextType::class, [
                'label' => 'State/Province',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 50]),
                ],
            ])
            ->add('billing_postal', TextType::class, [
                'label' => 'Zip/Postal Code',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 20]),
                ],
            ])
            ->add('billing_country', TextType::class, [
                'label' => 'Country',
                'data' => 'US',
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => 2, 'max' => 2]),
                ],
            ])
            ->add('billing_phone', TextType::class, [
                'label' => 'Phone',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 20]),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Create Subscription',
                'attr' => ['class' => 'btn btn-success'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'active_plans' => [],
        ]);

        $resolver->setAllowedTypes('active_plans', 'array');
    }

    private function buildPlanChoices(array $plans): array
    {
        $choices = [];
        foreach ($plans as $plan) {
            $label = sprintf('$%s %s (every %d days) - %s',
                number_format($plan->getAmount()->getAmount(), 2),
                ucfirst($plan->getBillingCycle()->getFrequency()),
                $plan->getBillingCycle()->getDayFrequency(),
                $plan->getPlanId()->getValue()
            );
            $choices[$label] = $plan->getPlanId()->getValue();
        }
        return $choices;
    }
}
