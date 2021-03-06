<?php

/**
 * @author Joseluis Laso <jlaso@joseluislaso.es>
 */
namespace JLaso\TranslationsBundle\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use JLaso\TranslationsBundle\Document\Repository\TranslationRepository;
use JLaso\TranslationsBundle\Document\Translation;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;

class SearchCommand extends ContainerAwareCommand
{

    /** @var  string */
    protected $name;
    /** @var  string */
    protected $description;

    protected function configure()
    {
        $this->name        = 'jlaso:translations:search';
        $this->description = 'search a key with specific data';
        $this
            ->setName($this->name)
            ->setDescription($this->description)
            ->addArgument('project', InputArgument::REQUIRED, 'project')
            ->addArgument('search', InputArgument::REQUIRED, 'search')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $project  = $input->getArgument('project');
        $search  = $input->getArgument('search');

        /** @var DocumentManager $dm */
        $dm = $container->get('doctrine.odm.mongodb.document_manager');
        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.default_entity_manager');

        /** @var ProjectRepository $projectRepository */
        $projectRepository = $em->getRepository('TranslationsBundle:Project');
        /** @var TranslationRepository $translationsRepository */
        $translationsRepository = $dm->getRepository('TranslationsBundle:Translation');

        if (intval($project)) {
            $project = $projectRepository->find($project);
        } else {
            $project = $projectRepository->findOneBy(array('name' => trim(strtolower($project))));
        }
        /** @var Project $project */
        if (!$project) {
            throw new \Exception('Project not found');
        }
        /** @var Translation[] $translations */
        $translations = $translationsRepository->findBy(array('projectId' => $project->getId()));

        foreach ($translations as $translation) {
            foreach ($translation->getTranslations() as $locale => $key) {
                if (preg_match('/'.$search.'/i', $key['message'], $match)) {
                    $output->writeln(sprintf("\tFound (%s) in <info>%s</info> in locale <comment>%s</comment> and catalog %s", $match[0], $translation->getKey(), $locale, $translation->getCatalog()));
                }
            }
        }

        $output->writeln(" done!");
    }
}
