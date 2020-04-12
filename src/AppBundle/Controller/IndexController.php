<?php

namespace AppBundle\Controller;

use AppBundle\Annotation\HideSoftDeleted;
use AppBundle\Controller\Utils\UserTrait;
use AppBundle\Entity\LocalBusinessRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

class IndexController extends AbstractController
{
    use UserTrait;

    const MAX_RESULTS = 3;

    /**
     * @Template
     * @HideSoftDeleted
     */
    public function indexAction(LocalBusinessRepository $repository)
    {
        $restaurants = $repository->findAllSorted('restaurant');
        $stores = $repository->findAllSorted('store');

        return array(
            'restaurants' => array_slice($restaurants, 0, self::MAX_RESULTS),
            'stores' => array_slice($stores, 0, self::MAX_RESULTS),
            'max_results' => self::MAX_RESULTS,
            'show_more' => count($restaurants) > self::MAX_RESULTS,
            'addresses_normalized' => $this->getUserAddresses(),
        );
    }
}
