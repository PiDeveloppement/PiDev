package com.melocode.pigestion.controller;

import com.melocode.pigestion.model.Question;
import com.melocode.pigestion.service.FeedbackService;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import javafx.scene.layout.StackPane;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;

public class ParticipantController {
    @FXML private Label lblQuestion, lblProgression;
    @FXML private TextField txtReponseParticipant;
    @FXML private TextArea txtCommentaire;
    @FXML private HBox starContainer;
    @FXML private VBox vboxEvaluation;
    @FXML private Button btnSuivant;

    private List<Question> listeQuestions = new ArrayList<>();
    private List<String> reponsesUtilisateur = new ArrayList<>();
    private int indexActuel = 0;
    private int etoilesSelectionnees = 0;

    private int idParticipantConnecte = 3; // Simulation Souhail
    private int idEventActuel = 1;

    private final FeedbackService fs = new FeedbackService();

    @FXML
    public void initialize() {
        try {
            listeQuestions = fs.chargerQuestionsAleatoires(idEventActuel);

            if (starContainer == null) return;

            if (listeQuestions.isEmpty()) {
                lblQuestion.setText("Aucune question disponible.");
                btnSuivant.setDisable(true);
                return;
            }

            setupStars();
            vboxEvaluation.setVisible(false);
            vboxEvaluation.setManaged(false);

            afficherQuestion();
        } catch (SQLException e) {
            e.printStackTrace();
        }
    }

    private void setupStars() {
        for (int i = 0; i < starContainer.getChildren().size(); i++) {
            final int val = i + 1;
            if (starContainer.getChildren().get(i) instanceof Button b) {
                b.setOnAction(e -> {
                    etoilesSelectionnees = val;
                    actualiserEtoiles();
                });
            }
        }
    }

    private void actualiserEtoiles() {
        for (int j = 0; j < starContainer.getChildren().size(); j++) {
            if (starContainer.getChildren().get(j) instanceof Button b) {
                b.setStyle(j < etoilesSelectionnees
                        ? "-fx-text-fill: #f1c40f; -fx-background-color: transparent; -fx-font-size: 30;"
                        : "-fx-text-fill: #bdc3c7; -fx-background-color: transparent; -fx-font-size: 30;");
            }
        }
    }

    private void afficherQuestion() {
        Question q = listeQuestions.get(indexActuel);
        lblProgression.setText("Question " + (indexActuel + 1) + " / " + listeQuestions.size());
        lblQuestion.setText(q.getTexteQuestion());

        if (indexActuel == listeQuestions.size() - 1) {
            vboxEvaluation.setVisible(true);
            vboxEvaluation.setManaged(true);
            btnSuivant.setText("TERMINER");
        }
    }

    @FXML
    private void handleSuivant() {
        String rep = txtReponseParticipant.getText().trim();
        if (rep.isEmpty()) {
            new Alert(Alert.AlertType.WARNING, "Réponse requise.").show();
            return;
        }

        reponsesUtilisateur.add(rep);

        if (indexActuel < listeQuestions.size() - 1) {
            indexActuel++;
            txtReponseParticipant.clear();
            afficherQuestion();
        } else {
            if (etoilesSelectionnees == 0) {
                new Alert(Alert.AlertType.WARNING, "Notez l'événement.").show();
                return;
            }
            sauvegarderEtChangerPage();
        }
    }

    private void sauvegarderEtChangerPage() {
        try {
            // 1. Sauvegarde SQL
            for (int i = 0; i < listeQuestions.size(); i++) {
                fs.enregistrerFeedbackComplet(idParticipantConnecte,
                        listeQuestions.get(i).getIdQuestion(),
                        reponsesUtilisateur.get(i),
                        txtCommentaire.getText(),
                        etoilesSelectionnees);
            }

            // 2. Chargement du FXML Resultat
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/melocode/pigestion/fxml/Resultat.fxml"));
            Parent root = loader.load();

            // 3. Init Data
            ResultatController resCtrl = loader.getController();
            resCtrl.setParticipantId(idParticipantConnecte);
            resCtrl.initData(listeQuestions, reponsesUtilisateur, txtCommentaire.getText(), etoilesSelectionnees);

            // 4. NAVIGATION CORRECTE : On remplace le contenu du Dashboard
            // On cherche le StackPane 'contentArea' définit dans main_layout.fxml
            StackPane contentArea = (StackPane) btnSuivant.getScene().lookup("#contentArea");

            if (contentArea != null) {
                contentArea.getChildren().clear();
                contentArea.getChildren().add(root);
            } else {
                // Secours si le Dashboard n'est pas trouvé
                btnSuivant.getScene().setRoot(root);
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}