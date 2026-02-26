package com.example.pidev;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.Label;
import javafx.scene.layout.VBox;
import javafx.scene.text.Text;
import java.io.IOException;
import java.net.URL;

public class MainController {

    private static MainController instance;

    public MainController() {
        instance = this;
    }

    public static MainController getInstance() {
        return instance;
    }

    @FXML private VBox pageContentContainer;
    @FXML private Label pageTitle;
    @FXML private Label pageSubtitle;

    // Sous-menus
    @FXML private VBox eventsSubmenu;
    @FXML private VBox usersSubmenu;
    @FXML private VBox sponsorsSubmenu;
    @FXML private VBox resourcesSubmenu;
    @FXML private VBox questionnairesSubmenu;

    // Flèches
    @FXML private Text eventsArrow;
    @FXML private Text usersArrow;
    @FXML private Text sponsorsArrow;
    @FXML private Text resourcesArrow;
    @FXML private Text questionnairesArrow;

    @FXML
    public void initialize() {
        // Charger une page vide ou dashboard au début
        showDashboard();
    }

    /**
     * Méthode utilisée par SalleController (Ligne 180)
     */
    public void setContent(Parent node) {
        if (pageContentContainer != null) {
            pageContentContainer.getChildren().setAll(node);
        }
    }

    public void loadPage(String fxmlFile, String title, String subtitle) {
        try {
            String path = "/com/example/pidev/fxml/resource/" + fxmlFile;
            URL url = getClass().getResource(path);

            if (url == null) {
                System.err.println("❌ FXML non trouvé: " + path);
                return;
            }

            Parent root = FXMLLoader.load(url);
            setContent(root);

            if (pageTitle != null) pageTitle.setText(title);
            if (pageSubtitle != null) pageSubtitle.setText(subtitle);

        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    // --- Actions Navigation ---
    @FXML public void showDashboard() { loadPage("dashboard.fxml", "Tableau de bord", "Aperçu général"); }
    @FXML public void showReservation() { loadPage("reservation.fxml", "Réservations", "Gérer les ressources"); }
    @FXML public void showEquipements() { loadPage("equipement.fxml", "Équipements", "Inventaire matériel"); }
    @FXML public void showSalles() { loadPage("salle.fxml", "Salles", "Gestion des espaces"); }

    // --- Logique Toggles ---
    @FXML private void toggleEventsMenu() { toggleMenu(eventsSubmenu, eventsArrow); }
    @FXML private void toggleUsersMenu() { toggleMenu(usersSubmenu, usersArrow); }
    @FXML private void toggleSponsorsMenu() { toggleMenu(sponsorsSubmenu, sponsorsArrow); }
    @FXML private void toggleResourcesMenu() { toggleMenu(resourcesSubmenu, resourcesArrow); }
    @FXML private void toggleQuestionnairesMenu() { toggleMenu(questionnairesSubmenu, questionnairesArrow); }

    private void toggleMenu(VBox submenu, Text arrow) {
        if (submenu != null) {
            boolean isVisible = submenu.isVisible();
            submenu.setVisible(!isVisible);
            submenu.setManaged(!isVisible);
            if (arrow != null) arrow.setText(isVisible ? "▶" : "▼");
        }
    }

    @FXML private void handleLogout() { System.exit(0); }

    // Ajoutez ou vérifiez ces méthodes dans MainController.java
    public void updateHeader(String title, String subtitle) {
        if (pageTitle != null) pageTitle.setText(title);
        if (pageSubtitle != null) pageSubtitle.setText(subtitle);
    }
}