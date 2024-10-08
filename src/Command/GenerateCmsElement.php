<?php

namespace Sas\CmsGenerator\Command;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\String\UnicodeString;

class GenerateCmsElement extends Command
{
    protected static $defaultName = 'sas:generate-cms:element';

    private array $pluginInfos;
    private string $projectDir;

    public function __construct(string $projectDir, array $pluginInfos)
    {
        parent::__construct(self::$defaultName);
        $this->pluginInfos = $pluginInfos;
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generates Cms Element Structure')
            ->addArgument('elementName', InputArgument::REQUIRED, 'The name of the element.')
            ->addArgument('pluginName', InputArgument::REQUIRED, 'Plugin Name');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->buildCmsElement(
            $input->getArgument('elementName'),
            $input->getArgument('pluginName')
        );

        $this->buildStorefrontElement(
            $input->getArgument('elementName'),
            $input->getArgument('pluginName')
        );

        $output->writeln(
            'CMS Element: '.$input->getArgument('elementName') . ' scaffolding installed successfully'
        );

        return Command::SUCCESS;
    }

    /**
     * Build the CMS Element file.
     *
     * @param string $elementName
     * @param string $pluginName
     * @return void
     * @throws \ReflectionException
     */
    private function buildCmsElement(string $elementName, string $pluginName)
    {
        // Get the plugin path
        $pluginPath = $this->determinePluginPath($pluginName);

        // Loop trough all stubs in /../../stubs/ and create an array of those
        $finder = new Finder();
        $finder->files()->in(__DIR__ . '/../../stubs/element/');

        // Generate the folder path
        $fileSystem = new Filesystem();
        $elementFolderPath = $pluginPath . '/Resources/app/administration/src/module/sw-cms/elements/' . $elementName . '/';

        // If folder path does not exist, create it
        if (!file_exists($elementFolderPath)) {
            $fileSystem->mkdir($elementFolderPath);
        }

        if ($finder->hasResults()) {

            foreach ($finder as $file) {

                $fileContent = file_get_contents($file->getPathname());
                $fileContent = str_replace('{{ name }}', $elementName, $fileContent);

                // Convert element-name to element_name for the twig block
                $twigBlockName = new UnicodeString($elementName);
                $fileContent = str_replace('{{ block }}', $twigBlockName->snake(), $fileContent);

                // Convert element-name to elementName for the label
                $labelName = new UnicodeString($elementName);
                $fileContent = str_replace('{{ label }}', $labelName->camel(), $fileContent);

                // Create the index file for the element
                if (strpos($file->getFilename(), 'base')) {
                    file_put_contents($elementFolderPath . '/' . 'index.js', $fileContent);
                }

                // create the files based on the type
                if (
                    strpos($file->getFilename(), 'component') ||
                    strpos($file->getFilename(), 'preview') ||
                    strpos($file->getFilename(), 'config')
                ) {

                    // Create the type string based on the stub file
                    if (strpos($file->getFilename(), 'component') ) {
                        $type = 'component';
                    } elseif (strpos($file->getFilename(), 'preview')) {
                        $type = 'preview';
                    } elseif (strpos($file->getFilename(), 'config')) {
                        $type = 'config';
                    }

                    // if folder does not exist, create it
                    if (!file_exists($elementFolderPath . '/' . $type)) {
                        $fileSystem->mkdir($elementFolderPath . '/' . $type);
                    }

                    if (strpos($file->getFilename(), 'twig')) {
                        file_put_contents($elementFolderPath . '/' . $type . '/sw-cms-el-'. $type . '-' . $elementName .'.html.twig', $fileContent);
                    }

                    if (strpos($file->getFilename(), 'scss')) {
                        file_put_contents($elementFolderPath . '/' . $type . '/sw-cms-el-'. $type . '-' .  $elementName .'.scss', $fileContent);
                    }

                    if (strpos($file->getFilename(), 'index')) {
                        file_put_contents($elementFolderPath . '/' . $type . '/index.js', $fileContent);
                    }
                }
            }
        }
    }

    /**
     * Build the CMS Storefront file.
     *
     * @param string $elementName
     * @param string $pluginName
     * @return void
     * @throws \ReflectionException
     */
    public function buildStorefrontElement(string $elementName, string $pluginName)
    {
        $storefrontTemplate = file_get_contents(__DIR__ . '/../../stubs/element/element.storefront.stub');

        // Replace placeholder within the stub file
        $storefrontTemplate = str_replace('{{ name }}', $elementName, $storefrontTemplate);

        // Convert element-name to element_name for Twig block
        $twigBlockName = new UnicodeString($elementName);
        $storefrontTemplate = str_replace('{{ block }}', $twigBlockName->snake(), $storefrontTemplate);

        // Generate the folder path
        $fileSystem = new Filesystem();
        $templateFolderPath = $this->determinePluginPath($pluginName) . '/Resources/views/storefront/element/';

        if (!file_exists($templateFolderPath)) {
            $fileSystem->mkdir($templateFolderPath);
        }

        // Move the generated file to the correct folder path
        file_put_contents($templateFolderPath . '/cms-element-' . $elementName . '.html.twig', $storefrontTemplate);
    }

    /**
     * Get information about the Plugin
     * @param string $name
     * @return string
     * @throws \ReflectionException
     */
    private function determinePluginPath(string $name): string
    {
        foreach ($this->pluginInfos as $pluginInfo) {
            if ($pluginInfo['name'] !== $name) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($pluginInfo['baseClass']);

            return dirname($reflectionClass->getFileName());
        }

        throw new \RuntimeException(sprintf('Cannot find plugin by name "%s"', $name));
    }
}
