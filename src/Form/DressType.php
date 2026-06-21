<?php

namespace App\Form;

use App\Entity\Dress;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class DressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => '款名',
                'attr' => ['placeholder' => '例如：鱼尾婚纱A款'],
            ])
            ->add('size', TextType::class, [
                'label' => '尺码',
                'attr' => ['placeholder' => '例如：S/M/L/XL 或 160/84A'],
            ])
            ->add('color', TextType::class, [
                'label' => '颜色',
                'attr' => ['placeholder' => '例如：白色、酒红色'],
            ])
            ->add('purchasePrice', MoneyType::class, [
                'label' => '买价 (¥)',
                'currency' => 'CNY',
                'scale' => 2,
                'attr' => ['placeholder' => '例如：5000'],
            ])
            ->add('deposit', MoneyType::class, [
                'label' => '押金 (¥)',
                'currency' => 'CNY',
                'scale' => 2,
                'attr' => ['placeholder' => '例如：2000'],
            ])
            ->add('dailyRate', MoneyType::class, [
                'label' => '日租金 (¥)',
                'currency' => 'CNY',
                'scale' => 2,
                'attr' => ['placeholder' => '例如：300'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => '状态',
                'choices' => [
                    '可出租' => Dress::STATUS_AVAILABLE,
                    '已租出' => Dress::STATUS_RENTED,
                    '清洗中' => Dress::STATUS_CLEANING,
                    '损坏待修' => Dress::STATUS_DAMAGED,
                ],
            ])
            ->add('photoFile', FileType::class, [
                'label' => '现状照片',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => '请上传有效的图片文件 (JPG, PNG, GIF, WEBP)',
                    ])
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dress::class,
        ]);
    }
}
