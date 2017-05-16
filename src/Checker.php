<?php

namespace Grobmeier\InvoiceChecker;

use Ddeboer\Imap\Search\Text\Subject;
use Ddeboer\Imap\SearchExpression;
use Ddeboer\Imap\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class Checker extends Command
{
    /** @var OutputInterface */
    private $output;

    protected function configure()
    {
        $this
            ->setName('mail')
            ->setDescription(
                'Checks your imap mail server for invoices'
            )->addArgument(
                'config',
                InputArgument::REQUIRED,
                'Your configuration file'
            );
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configFile = $input->getArgument('config');

        $config = Yaml::parse(file_get_contents($configFile));

        $server = new Server($config['host'], $config['port']);
        $connection = $server->authenticate($config['username'], $config['password']);

        $mailbox = $connection->getMailbox($config['search_folder']);

        $doneMailbox = $connection->getMailbox($config['done_folder']);


        foreach ($config['rules'] as $rule) {
            print_r($rule);
            $search = new SearchExpression();
            $search->addCondition(new Subject($rule['subject']));

            $messages = $mailbox->getMessages($search);

            if (sizeof($messages) > 0) {
                foreach ($messages as $message) {
                    print_r("Found invoice/receipt: " . $rule['name']);
                    $date = $message->getDate();
                    $year = $date->format('Y');
                    $month = $date->format('m');
                    $day = $date->format('d');

                    $target = str_replace('${month}', $month, $rule['invoice_folder']);
                    $target = str_replace('${year}', $year, $target);
                    $target = str_replace('${day}', $day, $target);

                    $attachments = $message->getAttachments();

                    file_put_contents(
                        $target . $attachments[0]->getFilename(),
                        $attachments[0]->getDecodedContent()
                    );

                    $message->move($doneMailbox);
                }
            }

        }
    }
}
