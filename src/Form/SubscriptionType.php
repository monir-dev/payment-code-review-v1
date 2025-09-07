<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use App\Entity\Plan;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
        $builder
            ->add('plan', EntityType::class, [
                'class' => Plan::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->where('p.status = :status')
                        ->setParameter('status', 'active')
                        ->orderBy('p.frequency', 'ASC')
                        ->addOrderBy('p.amount', 'ASC');
                },
                'choice_label' => function (Plan $plan) {
                    return sprintf('$%s %s (every %d days) - %s',
                        number_format($plan->getAmount(), 2),
                        ucfirst($plan->getFrequency()),
                        $plan->getDayFrequency(),
                        $plan->getPlanId()
                    );
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
                'data' => new \DateTime('+1 day'),
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
            ->add('billingPhone', TextType::class, [
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
        ]);
    }
}
