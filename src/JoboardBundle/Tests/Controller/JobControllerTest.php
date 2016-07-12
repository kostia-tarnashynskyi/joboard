<?php

namespace JoboardBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Doctrine\Bundle\DoctrineBundle\Command\DropDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\CreateSchemaDoctrineCommand;

class JobControllerTest extends WebTestCase
{
    private $em;
    private $application;

    public function setUp()
    {
        static::$kernel = static::createKernel();
        static::$kernel->boot();

        $this->application = new Application(static::$kernel);

        // удаляем базу
        $command = new DropDatabaseDoctrineCommand();
        $this->application->add($command);
        $input = new ArrayInput(array(
            'command' => 'doctrine:database:drop',
            '--force' => true
        ));
        $command->run($input, new NullOutput());

        // закрываем соединение с базой
        $connection = $this->application->getKernel()->getContainer()->get('doctrine')->getConnection();
        if ($connection->isConnected()) {
            $connection->close();
        }

        // создаём базу
        $command = new CreateDatabaseDoctrineCommand();
        $this->application->add($command);
        $input = new ArrayInput(array(
            'command' => 'doctrine:database:create',
        ));
        $command->run($input, new NullOutput());

        // создаём структуру
        $command = new CreateSchemaDoctrineCommand();
        $this->application->add($command);
        $input = new ArrayInput(array(
            'command' => 'doctrine:schema:create',
        ));
        $command->run($input, new NullOutput());

        // получаем Entity Manager
        $this->em = static::$kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        // загружаем фикстуры
        $client = static::createClient();
        $loader = new \Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader($client->getContainer());
        $loader->loadFromDirectory(static::$kernel->locateResource('@JoboardBundle/DataFixtures/ORM'));
        $purger = new \Doctrine\Common\DataFixtures\Purger\ORMPurger($this->em);
        $executor = new \Doctrine\Common\DataFixtures\Executor\ORMExecutor($this->em, $purger);
        $executor->execute($loader->getFixtures());
    }

    public function testIndex()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/job/');

        $kernel = static::createKernel();
        $kernel->boot();

        $this->assertEquals('JoboardBundle\Controller\JobController::indexAction', $client->getRequest()->attributes->get('_controller'));
        $this->assertTrue($crawler->filter('.jobs td.position:contains("Expired")')->count() == 0);

        // Находим категорию "дизайн"
        $categoryDesign = $this->em->getRepository('JoboardBundle:Category')->findOneBySlug('дизайн');

        // Находим категорию "программирование"
        $categoryProgramming = $this->em->getRepository('JoboardBundle:Category')->findOneBySlug('программирование');

        $maxJobsOnHomepage = $kernel->getContainer()->getParameter('max_jobs_on_homepage');
        $this->assertTrue($crawler->filter('.category-' . $categoryProgramming->getId() . ' tr')->count() <= $maxJobsOnHomepage);
        $this->assertTrue($crawler->filter('.category' . $categoryDesign->getId() . ' .more-jobs')->count() == 0);
        $this->assertTrue($crawler->filter('.category-' . $categoryProgramming->getId() . ' .more-jobs')->count() == 1);
        
        $job = $this->getMostRecentProgrammingJob();
        $this->assertTrue($crawler->filter('.category-' . $categoryProgramming->getId() . ' tr')->first()->filter(sprintf('a[href*="/%d/"]', $job->getId()))->count() == 1);

        $link = $crawler->selectLink('Web Разработчик')->first()->link();
        $client->click($link);
        $this->assertEquals('JoboardBundle\Controller\JobController::showAction', $client->getRequest()->attributes->get('_controller'));

        $this->assertEquals($job->getCompanySlug(), $client->getRequest()->attributes->get('company'));
        $this->assertEquals($job->getLocationSlug(), $client->getRequest()->attributes->get('location'));
        $this->assertEquals($job->getPositionSlug(), $client->getRequest()->attributes->get('position'));
        $this->assertEquals($job->getId(), $client->getRequest()->attributes->get('id'));
        
    }

    public function getMostRecentProgrammingJob()
    {
        $categoryProgramming = $this->em->getRepository('JoboardBundle:Category')->findOneBySlug('programmirovanie');
        $query = $this->em->createQuery('SELECT j from JoboardBundle:Job j
                                   LEFT JOIN j.category c
                                   WHERE c.slug = :slug AND j.expires_at > :date
                                   ORDER BY j.created_at DESC');
        $query->setParameter('slug', $categoryProgramming->getSlug());
        $query->setParameter('date', date('Y-m-d H:i:s', time()));
        $query->setMaxResults(1);

        return $query->getSingleResult();
    }
}