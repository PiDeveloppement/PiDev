<?php

namespace App\Controller\Resource;

use App\Entity\Resource\Salle;
use App\Form\Resource\SalleType;
use App\Repository\Resource\SalleRepository;
use App\Service\Resource\UnsplashService;
use App\Service\Resource\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/resource/salle')]
final class SalleController extends AbstractController
{
    #[Route('', name: 'app_resource_salle_index', methods: ['GET'])]
    public function index(SalleRepository $salleRepository): Response
    {
        return $this->render('resource/salle/index.html.twig', [
            'salles' => $salleRepository->findAll(),
            'total' => $salleRepository->count([]),
            'occupees' => $salleRepository->count(['status' => 'OCCUPEE']),
            'buildingsList' => $salleRepository->findAllUniqueBuildings(),
        ]);
    }

    #[Route('/new', name: 'app_resource_salle_new', methods: ['GET', 'POST'])]
    #[Route('/{id}/edit', name: 'app_resource_salle_edit', methods: ['GET', 'POST'])]
    public function form(Request $request, Salle $salle = null, EntityManagerInterface $entityManager, SluggerInterface $slugger, UnsplashService $unsplashService, AuditService $auditService): Response
    {
        if (!$salle) {
            $salle = new Salle();
        }

        $form = $this->createForm(SalleType::class, $salle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();
            // Pas de champ imagePath dans le formulaire, on utilise l'URL Unsplash par défaut

            // Cas 1: L'utilisateur a uploadé un fichier
            if ($imageFile instanceof UploadedFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $projectDir = $this->getParameter('kernel.project_dir');
                    if (is_string($projectDir)) {
                        $imageFile->move(
                            $projectDir . '/public/uploads/salles',
                            $newFilename
                        );
                    }
                    $salle->setImagePath('/uploads/salles/' . $newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', "Erreur lors de l'upload");
                }
            } 
            // Cas 2: Aucune image uploadée -> Génération auto via Unsplash
            else {
                $name = $salle->getName();
                if ($name !== null) {
                    $autoImageUrl = $unsplashService->getImageUrl($name);
                } else {
                    $autoImageUrl = null;
                }
                if ($autoImageUrl) {
                    $salle->setImagePath($autoImageUrl);
                } else {
                    // Fallback si Unsplash échoue
                    $salleName = $salle->getName() ?? 'Salle';
                    $salle->setImagePath('https://via.placeholder.com/400x300?text=' . urlencode($salleName));
                }
            }

            if (!$salle->getId()) {
                // Persister d'abord l'entité pour obtenir un ID
                $entityManager->persist($salle);
                $entityManager->flush();
                // Logger la création dans l'audit après persistance
                $auditService->logCreate($salle);
            } else {
                // Logger la modification dans l'audit
                $auditService->logUpdate($salle, [], $auditService->extractEntityValues($salle));
                $entityManager->flush();
            }
            return $this->redirectToRoute('app_resource_salle_index');
        }

        return $this->render('resource/salle/new.html.twig', [
            'salle' => $salle,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_resource_salle_show', methods: ['GET'])]
    public function show(Salle $salle): Response
    {
        return $this->render('resource/salle/show.html.twig', [
            'salle' => $salle,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_resource_salle_delete', methods: ['POST'])]
    public function delete(Request $request, Salle $salle, EntityManagerInterface $entityManager, AuditService $auditService): Response
    {
        if ($this->isCsrfTokenValid('delete'.$salle->getId(), $request->getPayload()->getString('_token'))) {
            // Logger la suppression dans l'audit avant de supprimer l'entité
            $auditService->logDelete($salle);
            
            $entityManager->remove($salle);
            $entityManager->flush();
        }
        return $this->redirectToRoute('app_resource_salle_index');
    }
}