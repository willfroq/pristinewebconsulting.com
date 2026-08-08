<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ConsultationRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Your name', 'constraints' => [new NotBlank(), new Length(max: 120)]])
            ->add('email', EmailType::class, ['label' => 'Work email', 'constraints' => [new NotBlank(), new Email(), new Length(max: 180)]])
            ->add('website', TextType::class, ['label' => 'Website or application (optional)', 'required' => false, 'constraints' => [new Length(max: 255)]])
            ->add('message', TextareaType::class, ['label' => 'What would you like help with?', 'attr' => ['rows' => 5], 'constraints' => [new NotBlank(), new Length(min: 20, max: 3000)]])
            ->add('submit', SubmitType::class, ['label' => 'Request a consultation →']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
