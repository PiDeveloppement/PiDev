package com.example.pidev;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.control.Button;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.scene.Parent;
import javafx.scene.text.Text;
import com.example.pidev.controller.event.CategoryListController;
import com.example.pidev.controller.event.CategoryFormController;
import com.example.pidev.model.event.EventCategory;

import java.io.IOException;

/**
 * Controller principal pour la navigation de l'application EventFlow
 * Gère le chargement des pages et les sous-menus déroulants
 * @author Ons Abdesslem
 * @version 4.0 - Final avec sous-menus toggle
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

    /**
     * Toggle sous-menu Événements
     */
    @FXML
    public void toggleEvents() {
        boolean isVisible = eventsSubmenu.isVisible();

        eventsSubmenu.setVisible(!isVisible);
        eventsSubmenu.setManaged(!isVisible);

        // Changer la flèche
        eventsArrow.setText(isVisible ? "▶" : "▼");

        // Changer le background
        String bgColor = isVisible ? "transparent" : "rgba(255,255,255,0.1)";
        eventsToggleBtn.setStyle(eventsToggleBtn.getStyle().replaceAll(
                "background-color: [^;]+",
                "background-color: " + bgColor
        ));

        System.out.println("📅 Menu Événements " + (isVisible ? "fermé" : "ouvert"));
    }

    /**
     * Toggle sous-menu Ressources
     */
    @FXML
    public void toggleResources() {
        boolean isVisible = resourcesSubmenu.isVisible();

        resourcesSubmenu.setVisible(!isVisible);
        resourcesSubmenu.setManaged(!isVisible);

        // Changer la flèche
        resourcesArrow.setText(isVisible ? "▶" : "▼");

        // Changer le background
        String bgColor = isVisible ? "transparent" : "rgba(255,255,255,0.1)";
        resourcesToggleBtn.setStyle(resourcesToggleBtn.getStyle().replaceAll(
                "background-color: [^;]+",
                "background-color: " + bgColor
        ));

        System.out.println("📦 Menu Ressources " + (isVisible ? "fermé" : "ouvert"));
    }


    // ==================== NAVIGATION METHODS ====================

    /**
     * 📊 Dashboard
     */
    @FXML
    public void showDashboard() {
        System.out.println("📊 Navigation vers Dashboard");
        loadContent("dashboard.fxml");
        highlightButton(dashboardBtn);
    }

    /**
     * 📋 Liste des événements
     */
    @FXML
    public void showEventsList() {
        System.out.println("📋 Navigation vers Liste des événements");
        loadContent("event/event-list.fxml");
        highlightButton(null); // Pas de highlight pour sous-menu
    }

    /**
     * 🗂️ Catégories
     */
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

    /**
     * Afficher le formulaire de catégorie
     */
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

    /**
     * 🎫 Billets
     */
    @FXML
    public void showTickets() {
        System.out.println("🎫 Navigation vers Billets");
        loadContent("event/ticket-list.fxml");
        highlightButton(null);
    }

    /**
     * 🏢 Salles
     */
    @FXML
    public void showRooms() {
        System.out.println("🏢 Navigation vers Salles");
        loadContent("resource/room-list.fxml");
        highlightButton(null);
    }

    /**
     * 💻 Équipements
     */
    @FXML
    public void showEquipments() {
        System.out.println("💻 Navigation vers Équipements");
        loadContent("resource/equipment-list.fxml");
        highlightButton(null);
    }

    /**
     * 📅 Réservations
     */
    @FXML
    public void showReservations() {
        System.out.println("📅 Navigation vers Réservations");
        loadContent("resource/reservation-list.fxml");
        highlightButton(null);
    }

    /**
     * 👥 Participants
     */
    @FXML
    public void showParticipants() {
        System.out.println("👥 Navigation vers Participants");
        loadContent("participant-list.fxml");
        highlightButton(participantsBtn);
    }

    /**
     * 💼 Sponsors
     */
    @FXML
    public void showSponsors() {
        System.out.println("💼 Navigation vers Sponsors");
        loadContent("sponsor-list.fxml");
        highlightButton(sponsorsBtn);
    }

    /**
     * 💰 Budget
     */
    @FXML
    public void showBudget() {
        System.out.println("💰 Navigation vers Budget");
        loadContent("budget-list.fxml");
        highlightButton(budgetBtn);
    }

    /**
     * ⚙️ Paramètres
     */
    @FXML
    public void showSettings() {
        System.out.println("⚙️ Navigation vers Paramètres");
        loadContent("settings.fxml");
        highlightButton(settingsBtn);
    }

    /**
     * 🚪 Déconnexion
     */
    @FXML
    public void handleLogout() {
        System.out.println("🚪 Déconnexion...");
        System.exit(0);
    }


    // ==================== UTILITY METHODS ====================

    /**
     * Charger un fichier FXML
     */
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

    /**
     * Mettre en évidence le bouton actif
     */
    private void highlightButton(Button activeButton) {
        // Reset tous les boutons
        resetButtonStyle(dashboardBtn);
        resetButtonStyle(categoriesBtn);
        resetButtonStyle(participantsBtn);
        resetButtonStyle(sponsorsBtn);
        resetButtonStyle(budgetBtn);
        resetButtonStyle(settingsBtn);

        // Highlight le bouton actif
        if (activeButton != null) {
            activeButton.setStyle(activeButton.getStyle() +
                    "-fx-background-color: rgba(255,255,255,0.15);");
        }
    }

    /**
     * Reset le style d'un bouton
     */
    private void resetButtonStyle(Button btn) {
        if (btn != null) {
            btn.setStyle(btn.getStyle().replaceAll(
                    "-fx-background-color: rgba\\(255,255,255,0\\.15\\);",
                    ""
            ));
        }
    }

    /**
     * Setup hover effects pour tous les boutons
     */
    private void setupHoverEffects() {
        // Cette méthode peut être étendue pour ajouter des effets hover
        // Pour l'instant, les effets sont gérés en CSS
    }
}