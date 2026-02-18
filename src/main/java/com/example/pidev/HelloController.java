package com.example.pidev;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.control.Button;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.scene.Parent;
import javafx.scene.text.Text;
import com.example.pidev.controller.event.*;
import com.example.pidev.model.event.Event;
import com.example.pidev.model.event.EventCategory;
import com.example.pidev.model.event.EventTicket;

import java.io.IOException;

/**
 * Controller principal pour la navigation de l'application EventFlow
 * Gère le chargement des pages et les sous-menus déroulants
 * @author Ons Abdesslem
 * @version 7.0 - Avec module tickets
 */
public class HelloController {

    // ==================== FXML ELEMENTS ====================

    @FXML private StackPane contentArea;

    // Boutons principaux
    @FXML private Button dashboardBtn;
    @FXML private Button eventsToggleBtn;
    @FXML private Button resourcesToggleBtn;
    @FXML private Button categoriesBtn;
    @FXML private Button participantsBtn;
    @FXML private Button sponsorsBtn;
    @FXML private Button budgetBtn;
    @FXML private Button settingsBtn;

    // Sous-menus
    @FXML private VBox eventsSubmenu;
    @FXML private VBox resourcesSubmenu;

    // Flèches toggle
    @FXML private Text eventsArrow;
    @FXML private Text resourcesArrow;


    // ==================== INITIALIZATION ====================

    @FXML
    public void initialize() {
        System.out.println("✅ HelloController initialisé avec sous-menus");

        // Hover effects pour tous les boutons
        setupHoverEffects();

        // Charger Catégories par défaut
        showCategories();
    }


    // ==================== TOGGLE METHODS ====================

    @FXML
    public void toggleEvents() {
        boolean isVisible = eventsSubmenu.isVisible();

        eventsSubmenu.setVisible(!isVisible);
        eventsSubmenu.setManaged(!isVisible);
        eventsArrow.setText(isVisible ? "▶" : "▼");

        String bgColor = isVisible ? "transparent" : "rgba(255,255,255,0.1)";
        eventsToggleBtn.setStyle(eventsToggleBtn.getStyle().replaceAll(
                "background-color: [^;]+",
                "background-color: " + bgColor
        ));

        System.out.println("📅 Menu Événements " + (isVisible ? "fermé" : "ouvert"));
    }

    @FXML
    public void toggleResources() {
        boolean isVisible = resourcesSubmenu.isVisible();

        resourcesSubmenu.setVisible(!isVisible);
        resourcesSubmenu.setManaged(!isVisible);
        resourcesArrow.setText(isVisible ? "▶" : "▼");

        String bgColor = isVisible ? "transparent" : "rgba(255,255,255,0.1)";
        resourcesToggleBtn.setStyle(resourcesToggleBtn.getStyle().replaceAll(
                "background-color: [^;]+",
                "background-color: " + bgColor
        ));

        System.out.println("📦 Menu Ressources " + (isVisible ? "fermé" : "ouvert"));
    }


    // ==================== NAVIGATION METHODS ====================

    // ========== DASHBOARD (temporaire) ==========
    @FXML
    public void showDashboard() {
        System.out.println("📊 Dashboard (page temporaire)");
        showCategories();
        highlightButton(dashboardBtn);
    }

    // ========== LISTE DES ÉVÉNEMENTS ==========
    @FXML
    public void showEventsList() {
        System.out.println("📋 Navigation vers Liste des événements");
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/event-list.fxml")
            );
            Parent page = loader.load();

            EventListController controller = loader.getController();
            if (controller != null) {
                controller.setHelloController(this);
                System.out.println("✅ EventListController connecté");
            }

            contentArea.getChildren().clear();
            contentArea.getChildren().add(page);
            highlightButton(null);

        } catch (IOException e) {
            System.err.println("❌ Erreur: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void showEventForm(Event event) {
        try {
            System.out.println("📝 Formulaire événement");

            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/event-form.fxml")
            );
            Parent page = loader.load();

            EventFormController controller = loader.getController();
            controller.setHelloController(this);

            if (event != null) {
                controller.setEvent(event);
            }

            contentArea.getChildren().clear();
            contentArea.getChildren().add(page);

        } catch (IOException e) {
            System.err.println("❌ Erreur: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void showEventView(Event event) {
        try {
            System.out.println("👁️ Vue détaillée de l'événement: " + event.getTitle());

            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/event-view.fxml")
            );
            Parent page = loader.load();

            EventViewController controller = loader.getController();
            controller.setHelloController(this);
            controller.setEvent(event);

            contentArea.getChildren().clear();
            contentArea.getChildren().add(page);

        } catch (IOException e) {
            System.err.println("❌ Erreur chargement vue événement: " + e.getMessage());
            e.printStackTrace();
        }
    }

    // ========== TICKETS ==========

    public void showTicketsList() {
        System.out.println("🎫 Navigation vers Liste des tickets");
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/ticket-list.fxml")
            );
            Parent page = loader.load();

            EventTicketListController controller = loader.getController();
            if (controller != null) {
                controller.setHelloController(this);
                System.out.println("✅ EventTicketListController connecté");
            }

            contentArea.getChildren().clear();
            contentArea.getChildren().add(page);
            highlightButton(null);

        } catch (IOException e) {
            System.err.println("❌ Erreur: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void showTicketView(EventTicket ticket) {
        try {
            System.out.println("👁️ Vue détaillée du ticket: " + ticket.getTicketCode());

            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/ticket-view.fxml")
            );
            Parent page = loader.load();

            EventTicketViewController controller = loader.getController();
            controller.setHelloController(this);
            controller.setTicket(ticket);

            contentArea.getChildren().clear();
            contentArea.getChildren().add(page);

        } catch (IOException e) {
            System.err.println("❌ Erreur chargement vue ticket: " + e.getMessage());
            e.printStackTrace();
        }
    }

    // ========== CATÉGORIES ==========
    @FXML
    public void showCategories() {
        System.out.println(" 🗂️ Navigation vers Catégories");

        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/category-list.fxml")
            );
            Parent page = loader.load();

            CategoryListController controller = loader.getController();
            if (controller != null) {
                controller.setHelloController(this);
                System.out.println("✅ CategoryListController connecté");
            }

            contentArea.getChildren().clear();
            contentArea.getChildren().add(page);
            highlightButton(categoriesBtn);

        } catch (IOException e) {
            System.err.println("❌ Erreur: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void showCategoryForm(EventCategory category) {
        try {
            System.out.println("📝 Formulaire catégorie");

            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/category-form.fxml")
            );
            Parent page = loader.load();

            CategoryFormController controller = loader.getController();
            controller.setHelloController(this);

            if (category != null) {
                controller.setCategory(category);
            }

            contentArea.getChildren().clear();
            contentArea.getChildren().add(page);
            highlightButton(categoriesBtn);

        } catch (IOException e) {
            System.err.println("❌ Erreur: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void showCategoryView(EventCategory category) {
        try {
            System.out.println("👁️ Vue détaillée de la catégorie");

            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/event/category-view.fxml")
            );
            Parent page = loader.load();

            CategoryViewController controller = loader.getController();
            controller.setHelloController(this);
            controller.setCategory(category);

            contentArea.getChildren().clear();
            contentArea.getChildren().add(page);
            highlightButton(categoriesBtn);

        } catch (IOException e) {
            System.err.println("❌ Erreur chargement vue catégorie: " + e.getMessage());
            e.printStackTrace();
        }
    }

    // ========== BILLETS (mis à jour) ==========
    @FXML
    public void showTickets() {
        System.out.println("🎫 Navigation vers Billets");
        showTicketsList();  // Maintenant ça fonctionne vraiment
        highlightButton(null);
    }

    // ========== RESSOURCES ==========
    @FXML
    public void showRooms() {
        System.out.println("🏢 Navigation vers Salles");
        loadContent("resource/room-list.fxml");
        highlightButton(null);
    }

    @FXML
    public void showEquipments() {
        System.out.println("💻 Navigation vers Équipements");
        loadContent("resource/equipment-list.fxml");
        highlightButton(null);
    }

    @FXML
    public void showReservations() {
        System.out.println("📅 Navigation vers Réservations");
        loadContent("resource/reservation-list.fxml");
        highlightButton(null);
    }

    // ========== AUTRES ==========
    @FXML
    public void showParticipants() {
        System.out.println("👥 Navigation vers Participants");
        loadContent("participant-list.fxml");
        highlightButton(participantsBtn);
    }

    @FXML
    public void showSponsors() {
        System.out.println("💼 Navigation vers Sponsors");
        loadContent("sponsor-list.fxml");
        highlightButton(sponsorsBtn);
    }

    @FXML
    public void showBudget() {
        System.out.println("💰 Navigation vers Budget");
        loadContent("budget-list.fxml");
        highlightButton(budgetBtn);
    }

    @FXML
    public void showSettings() {
        System.out.println("⚙️ Navigation vers Paramètres");
        loadContent("settings.fxml");
        highlightButton(settingsBtn);
    }

    @FXML
    public void handleLogout() {
        System.out.println("🚪 Déconnexion...");
        System.exit(0);
    }


    // ==================== UTILITY METHODS ====================

    private void loadContent(String fxmlPath) {
        try {
            FXMLLoader loader = new FXMLLoader(
                    getClass().getResource("/com/example/pidev/fxml/" + fxmlPath)
            );
            Parent page = loader.load();

            contentArea.getChildren().clear();
            contentArea.getChildren().add(page);

            System.out.println("✅ Page chargée: " + fxmlPath);

        } catch (IOException e) {
            System.err.println("❌ Erreur: " + fxmlPath);
            e.printStackTrace();
        }
    }

    private void highlightButton(Button activeButton) {
        resetButtonStyle(dashboardBtn);
        resetButtonStyle(categoriesBtn);
        resetButtonStyle(participantsBtn);
        resetButtonStyle(sponsorsBtn);
        resetButtonStyle(budgetBtn);
        resetButtonStyle(settingsBtn);

        if (activeButton != null) {
            activeButton.setStyle(activeButton.getStyle() +
                    "-fx-background-color: rgba(255,255,255,0.15);");
        }
    }

    private void resetButtonStyle(Button btn) {
        if (btn != null) {
            btn.setStyle(btn.getStyle().replaceAll(
                    "-fx-background-color: rgba\\(255,255,255,0\\.15\\);",
                    ""
            ));
        }
    }

    private void setupHoverEffects() {
        // Géré par CSS
    }
}