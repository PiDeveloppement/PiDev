package com.melocode.pigestion;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.Label;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;
import javafx.scene.text.Text;
import java.io.IOException;
import java.net.URL;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;

public class main_layout {

    @FXML private StackPane contentArea;
    @FXML private Label pageTitle, pageSubtitle, navDateLabel, navTimeLabel;

    // Sous-menus et flèches
    @FXML private VBox eventsSubmenu, usersSubmenu, sponsorsSubmenu, questionnairesSubmenu;
    @FXML private Text eventsArrow, usersArrow, sponsorsArrow, questionnairesArrow;

    private static main_layout instance;

    public main_layout() {
        instance = this;
    }

    public static main_layout getInstance() {
        return instance;
    }

    @FXML
    public void initialize() {
        setupDateTime();
        // Charge l'éditeur de questions par défaut au démarrage
        showQuestionEditor();
    }

    private void setupDateTime() {
        LocalDateTime now = LocalDateTime.now();
        navDateLabel.setText(now.format(DateTimeFormatter.ofPattern("EEEE dd MMMM yyyy")));
        navTimeLabel.setText(now.format(DateTimeFormatter.ofPattern("HH:mm:ss")));
    }

    // Gestion du contenu (Supporte 2 ou 3 arguments pour la compatibilité)
    public void setContent(Parent root, String title) {
        setContent(root, title, "");
    }

    public void setContent(Parent root, String title, String subtitle) {
        if (pageTitle != null) pageTitle.setText(title);
        if (pageSubtitle != null) pageSubtitle.setText(subtitle);
        if (contentArea != null) {
            contentArea.getChildren().setAll(root);
        }
    }

    private void loadPage(String fxmlFile, String title, String subtitle) {
        try {
            // Chemin absolu vers tes ressources FXML
            String path = "/com/melocode/pigestion/fxml/" + fxmlFile;
            URL fileUrl = getClass().getResource(path);

            if (fileUrl == null) {
                System.err.println("ERREUR : Fichier FXML introuvable à l'emplacement : " + path);
                return;
            }

            FXMLLoader loader = new FXMLLoader(fileUrl);
            Parent root = loader.load();
            setContent(root, title, subtitle);

        } catch (IOException e) {
            System.err.println("ERREUR de chargement de la page " + fxmlFile + " : " + e.getMessage());
            e.printStackTrace();
        }
    }

    // --- Méthodes de Navigation (Noms mis à jour selon ton dossier) ---

    @FXML
    private void showDashboard() {
        // D'après ton image, tu utilises Resultat.fxml pour tes stats/dashboard
        loadPage("Resultat.fxml", "Dashboard", "Statistiques et aperçu global");
    }

    @FXML
    private void showQuestionEditor() {
        loadPage("form_question.fxml", "Questions", "Gestion de la banque de données");
    }

    @FXML
    private void showParticipantQuiz() {
        loadPage("Participant.fxml", "Passer le Quiz", "Interface d'examen");
    }

    @FXML
    private void showResultats() {
        // Correspond à Historique.fxml dans tes ressources
        loadPage("Historique.fxml", "Historique", "Consultation des anciens scores");
    }

    // --- Gestion de la Sidebar (Toggle) ---

    private void toggle(VBox menu, Text arrow) {
        if (menu != null) {
            boolean visible = menu.isVisible();
            menu.setVisible(!visible);
            menu.setManaged(!visible);
            if (arrow != null) {
                arrow.setText(visible ? "▶" : "▼");
            }
        }
    }

    @FXML private void toggleEvents() { toggle(eventsSubmenu, eventsArrow); }
    @FXML private void toggleUsers() { toggle(usersSubmenu, usersArrow); }
    @FXML private void toggleSponsors() { toggle(sponsorsSubmenu, sponsorsArrow); }
    @FXML private void toggleQuestionnaires() { toggle(questionnairesSubmenu, questionnairesArrow); }

    @FXML
    private void handleExit() {
        System.exit(0);
    }
}