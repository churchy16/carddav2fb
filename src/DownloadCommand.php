<?php

namespace Andig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class DownloadCommand extends Command
{
    use ConfigTrait;

    const JSON_OPTIONS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE;

    protected function configure()
    {
        $this->setName('download')
            ->setDescription('Load from CardDAV server')
            ->addArgument('filename', InputArgument::OPTIONAL, 'raw vcards json file')
            ->addOption('image', 'i', InputOption::VALUE_NONE, 'download images')
            ->addOption('raw', 'r', InputOption::VALUE_REQUIRED, 'export raw vcards to json file');

        $this->addConfig();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadConfig($input);

        $vcards = array();
        $substitutes = ($input->getOption('image')) ? ['PHOTO'] : [];
        
        if ($filename = $input->getArgument('filename')) {
            // read from file
            $vcards = json_decode(file_get_contents($filename));
            if (!is_array($vcards)) {
                throw new \Exception(sprintf('Could not read unparsed vcards from %s', $filename));
            }
        } else {
            // download
            foreach ($this->config['server'] as $server) {
                $progress = new ProgressBar($output);
                error_log("Downloading vCard(s) from account ".$server['user']);
                
                $backend = backendProvider($server);
                $progress->start();
                $downloaded = download($backend, $substitutes, function () use ($progress) {
                    $progress->advance();
                });
                $progress->finish();
                $vcards = array_merge($vcards, $downloaded);
                error_log(sprintf("\nDownloaded %d vCard(s)", count($vcards)));
            }

            if ($file = $input->getOption('raw')) {
                $json = json_encode($vcards, self::JSON_OPTIONS);
                file_put_contents($file, $json);
            }
        }

        // dissolve
        error_log("Dissolving groups (e.g. iCloud)");
        $cards = dissolveGroups($vcards);
        $json = json_encode($cards, self::JSON_OPTIONS);

        echo $json;
    }
}
