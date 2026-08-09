<?php

declare(strict_types=1);

namespace App\Command;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:generate-qrcode',
    description: 'Generate a PNG QR code for a website URL.',
)]
final class GenerateQrCodeCommand extends Command
{
    public function __construct(private readonly KernelInterface $kernel)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED, 'Website URL to encode')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'PNG output path, relative to the project directory', 'public/images/website-qrcode.png')
            ->addOption('size', null, InputOption::VALUE_REQUIRED, 'Image width and height in pixels', '300');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = $input->getArgument('url');
        $outputPath = $input->getOption('output');
        $size = filter_var($input->getOption('size'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 100, 'max_range' => 2000],
        ]);

        if (!is_string($url) || !$this->isWebsiteUrl($url)) {
            $io->error('Provide a valid HTTP or HTTPS website URL.');

            return Command::INVALID;
        }

        if (!is_string($outputPath) || '' === trim($outputPath) || !str_ends_with(strtolower($outputPath), '.png')) {
            $io->error('The output path must be a PNG filename.');

            return Command::INVALID;
        }

        if (false === $size) {
            $io->error('The size must be an integer between 100 and 2000 pixels.');

            return Command::INVALID;
        }

        $path = $this->resolveOutputPath($outputPath);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $io->error(sprintf('Unable to create the output directory: %s', $directory));

            return Command::FAILURE;
        }

        try {
            $qrCode = new QrCode(
                data: $url,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: $size,
                margin: 10,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
            );

            (new PngWriter())->write($qrCode)->saveToFile($path);
        } catch (\Throwable $exception) {
            $io->error(sprintf('Unable to generate the QR code: %s', $exception->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('QR code for %s written to %s', $url, $path));

        return Command::SUCCESS;
    }

    private function isWebsiteUrl(string $url): bool
    {
        $parts = parse_url($url);

        return false !== filter_var($url, FILTER_VALIDATE_URL)
            && is_array($parts)
            && isset($parts['host'], $parts['scheme'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true);
    }

    private function resolveOutputPath(string $outputPath): string
    {
        if ('/' === $outputPath[0]) {
            return $outputPath;
        }

        return $this->kernel->getProjectDir().'/'.$outputPath;
    }
}
