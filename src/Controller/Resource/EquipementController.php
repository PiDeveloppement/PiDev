<?php

namespace App\Controller\Resource;

use App\Entity\Resource\Equipement;
use App\Form\EquipementType;
use App\Repository\Resource\EquipementRepository;
use App\Service\Resource\UnsplashService; // Ton service Unsplash
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/resource/equipement')]
class EquipementController extends AbstractController
{
#[Route('/', name: 'app_resource_equipement_index')]
public function index(Request $request, EquipementRepository $repository): Response
{
    // On récupère les paramètres de l'URL (?category=...&term=...)
    $category = $request->query->get('category');
    $term = $request->query->get('term');

    // On appelle une méthode personnalisée du Repository
    $equipements = $repository->findWithFilters($category, $term);

    // On récupère la liste unique des catégories pour remplir le <select> dynamiquement
    $categoriesList = $repository->findAllUniqueCategories();

    return $this->render('resource/equipement/index.html.twig', [
        'equipements' => $equipements,
        'categoriesList' => $categoriesList,
        'selectedCategory' => $category,
        'searchTerm' => $term,
    ]);
}

    #[Route('/new', name: 'app_resource_equipement_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager, 
        UnsplashService $unsplash // Injection du service Unsplash
    ): Response {
        $equipement = new Equipement();
        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1. Logique Unsplash automatique
            // On récupère l'image basée sur le nom de l'équipement
            $url = $unsplash->getImageUrl($equipement->getName());
            $equipement->setImagePath($url);

            // 2. Enregistrement
            $entityManager->persist($equipement);
            $entityManager->flush();

            $this->addFlash('success', 'Équipement ajouté via Unsplash !');

            return $this->redirectToRoute('app_resource_equipement_index');
        }

        return $this->render('resource/equipement/new.html.twig', [
            'form' => $form->createView(),
            'equipement' => $equipement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_resource_equipement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Equipement $equipement, EntityManagerInterface $entityManager, UnsplashService $unsplash): Response
    {
        // On réutilise le même formulaire que pour l'ajout !
        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Optionnel : si le nom a changé, on peut mettre à jour l'image Unsplash
            $url = $unsplash->getImageUrl($equipement->getName());
            $equipement->setImagePath($url);

            $entityManager->flush(); // Pas besoin de persist() pour une modification

            $this->addFlash('info', 'L\'équipement a été mis à jour.');
            return $this->redirectToRoute('app_resource_equipement_index');
        }

        return $this->render('resource/equipement/edit.html.twig', [
            'equipement' => $equipement,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_resource_equipement_delete', methods: ['POST', 'GET'])]
    public function delete(Request $request, Equipement $equipement, EntityManagerInterface $entityManager): Response
    {
        // Pour la suppression simple, on peut faire un lien direct ou un petit formulaire
        $entityManager->remove($equipement);
        $entityManager->flush();

        $this->addFlash('danger', 'L\'équipement a été supprimé.');
        
        return $this->redirectToRoute('app_resource_equipement_index');
    }


    #[Route('/search', name: 'app_resource_equipement_search', methods: ['GET'])]
    public function search(Request $request, EquipementRepository $repository): Response
    {
        // Utilise l'objet Request de Symfony plutôt que $_GET
        $term = $request->query->get('term');
        $category = $request->query->get('category');

        $results = $repository->searchByTermAndCategory($term, $category);

        return $this->render('resource/equipement/index.html.twig', [
            'equipements' => $results,
        ]);
    }
    
}