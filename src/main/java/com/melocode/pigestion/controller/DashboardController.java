package com.melocode.pigestion.controller;

import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.Label;
import javafx.scene.layout.StackPane;
import java.io.IOException;
import java.net.URL;

public class DashboardController {

    @FXML private StackPane contentArea;
    @FXML private Label lblPageTitle;

    @FXML
    public void initialize() {
        // Optionnel : charger une page par défaut
        // showQuestionEditor();
    }

    private void loadPage(String fxmlFile, String title) {
        try {
            if (lblPageTitle != null) lblPageTitle.setText(title);

            // Chemin relatif au package du Controller
            URL fileUrl = getClass().getResource("/com/melocode/pigestion/fxml/" + fxmlFile);

            if (fileUrl == null) {
                System.err.println("Fichier FXML introuvable : " + fxmlFile);
                return;
            }

            Parent root = FXMLLoader.load(fileUrl);
            contentArea.getChildren().setAll(root);

        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    @FXML private void showQuestionEditor() { loadPage("Question.fxml", "⚙ ÉDITEUR"); }
    @FXML private void showParticipantQuiz() { loadPage("Participant.fxml", "📝 QUIZ"); }
    @FXML private void showResultats() { loadPage("Resultat.fxml", "📊 STATS"); }
    @FXML private void handleExit() { System.exit(0); }
}