<?php

namespace JLaso\TranslationsBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * LanguageRepository
 */
class TranslationLogRepository extends EntityRepository
{

    public function getLast()
    {

        return 1;

    }


}
