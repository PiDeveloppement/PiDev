<?php

namespace App\Controller\Resource;

use App\Entity\Resource\Salle;
use App\Form\Resource\SalleType;
use App\Repository\Resource\SalleRepository;
use App\Service\Resource\UnsplashService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function form(Request $request, Salle $salle = null, EntityManagerInterface $entityManager, SluggerInterface $slugger, UnsplashService $unsplashService): Response
    {
        if (!$salle) {
            $salle = new Salle();
        }

        $form = $this->createForm(SalleType::class, $salle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageUpload')->getData();
            $imagePath = $form->get('imagePath')->getData();

            // Cas 1: L'utilisateur a uploadé un fichier
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/salles',
                        $newFilename
                    );
                    $salle->setImagePath('/uploads/salles/' . $newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', "Erreur lors de l'upload");
                }
            } 
            // Cas 2: L'utilisateur a mis une URL manuelle
            elseif ($imagePath) {
                $salle->setImagePath($imagePath);
            }
            // Cas 3: Aucune image choisie -> Génération auto via Unsplash
            else {
                $autoImageUrl = $unsplashService->getImageUrl($salle->getName());
                if ($autoImageUrl) {
                    $salle->setImagePath($autoImageUrl);
                } else {
                    // Fallback si Unsplash échoue
                    $salle->setImagePath('https://via.placeholder.com/400x300?text=' . urlencode($salle->getName()));
                }
            }

            if (!$salle->getId()) {
                $entityManager->persist($salle);
            }
            
            $entityManager->flush();
            return $this->redirectToRoute('app_resource_salle_index');
        }

        return $this->render('resource/salle/new.html.twig', [
            'salle' => $salle,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_resource_salle_delete', methods: ['POST'])]
    public function delete(Request $request, Salle $salle, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$salle->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($salle);
            $entityManager->flush();
        }
        return $this->redirectToRoute('app_resource_salle_index');
    }
}