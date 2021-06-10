<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\UpdateUser;
use App\Repository\UserRepository;
use Google\Cloud\BigQuery\BigQueryClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:fetch:active-users',
    description: 'Fetch new actives users from Github Archive',
)]
class FetchActiveUsersCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private MessageBusInterface $bus,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->addArgument('limit', InputArgument::REQUIRED, 'Limit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $date = new \DateTime('-2 days');

        $limit = intval($input->getArgument('limit'));

        if ($limit <= 0) {
            $io->error('Limit must be superior to zero.');

            return Command::FAILURE;
        }

        $bigQuery = new BigQueryClient([
            'keyFile' => json_decode((string) file_get_contents(__DIR__ . '/../../gc-key.json'), true),
        ]);

        $query = 'SELECT actor.id FROM `githubarchive.day.' . $date->format('Ymd') . '` GROUP BY actor.id LIMIT ' . $limit;

        $queryJobConfig = $bigQuery->query($query);
        $queryResults   = $bigQuery->runQuery($queryJobConfig);

        $users = $this->userRepository->findAllId();

        $newUser = 0;

        foreach ($queryResults as $result) {
            if (!in_array($result['id'], $users)) {
                $this->bus->dispatch(
                    new UpdateUser($result['id'])
                );
                ++$newUser;
            }
        }

        $io->success($newUser . ' new user(s) added!');

        return Command::SUCCESS;
    }
}
