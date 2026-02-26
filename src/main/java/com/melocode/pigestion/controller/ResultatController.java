package com.melocode.pigestion.controller;

import com.melocode.pigestion.model.Question;
import com.melocode.pigestion.service.FeedbackService;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import javafx.scene.paint.Color;
import java.sql.SQLException;
import java.util.List;

public class ResultatController {

    @FXML private VBox vboxCorrection;
    @FXML private Label lblScore, lblStatus;
    @FXML private HBox editStarContainer;
    @FXML private TextArea txtAreaComment;
    @FXML private Button btnCertificat;

    private int participantId;
    private int feedbackId; // <--- CORRECTION : Variable déclarée ici pour être vue par onModifier/onSupprimer
    private int noteModifiee = 0;
    private final FeedbackService fs = new FeedbackService();

    public void setParticipantId(int id) {
        this.participantId = id;
    }

    /**
     * Initialise les données de correction et de score
     * CORRECTION : Ajout du paramètre int idF au début
     */
    public void initData(int idF, List<Question> questions, List<String> reponses, String commentaire, int noteInitial) {
        this.feedbackId = idF; // <--- CORRECTION : On initialise l'ID ici

        int score = 0;
        vboxCorrection.getChildren().clear();
        vboxCorrection.setSpacing(15);

        for (int i = 0; i < questions.size(); i++) {
            Question q = questions.get(i);
            String repU = (i < reponses.size()) ? reponses.get(i) : "Pas de réponse";

            boolean estCorrect = repU.equalsIgnoreCase(q.getBonneReponse());
            if (estCorrect) score++;

            VBox bloc = new VBox(8);
            bloc.setStyle("-fx-background-color: #f8fafc; -fx-padding: 15; -fx-background-radius: 10; " +
                    "-fx-border-color: " + (estCorrect ? "#dcfce7;" : "#fee2e2;") +
                    "-fx-border-width: 2; -fx-border-radius: 10;");

            Label lblQ = new Label((i + 1) + ". " + q.getTexteQuestion());
            lblQ.setStyle("-fx-font-weight: bold; -fx-font-size: 14px; -fx-text-fill: #1e293b;");

            Label lblR = new Label((estCorrect ? "✔ " : "✘ ") + "Votre réponse : " + repU);
            lblR.setStyle("-fx-font-weight: bold;");
            lblR.setTextFill(estCorrect ? Color.web("#16a34a") : Color.web("#dc2626"));

            bloc.getChildren().addAll(lblQ, lblR);

            if (!estCorrect) {
                Label lblCorrect = new Label("💡 Réponse attendue : " + q.getBonneReponse());
                lblCorrect.setStyle("-fx-font-style: italic; -fx-text-fill: #64748b;");
                bloc.getChildren().add(lblCorrect);
            }

            vboxCorrection.getChildren().add(bloc);
        }

        lblScore.setText("Score Final : " + score + " / " + questions.size());

        double pourcentage = (double) score / questions.size();
        if (pourcentage >= 0.5) {
            lblStatus.setText("FÉLICITATIONS : RÉUSSI");
            lblStatus.setTextFill(Color.web("#16a34a"));
            if (btnCertificat != null) btnCertificat.setVisible(true);
        } else {
            lblStatus.setText("DOMMAGE : ÉCHEC");
            lblStatus.setTextFill(Color.web("#dc2626"));
            if (btnCertificat != null) btnCertificat.setVisible(false);
        }

        this.noteModifiee = noteInitial;
        if (txtAreaComment != null) txtAreaComment.setText(commentaire);

        setupEditStars();
        actualiserEtoiles();
    }

    /**
     * Configure les boutons d'étoiles pour permettre la modification
     */
    private void setupEditStars() {
        if (editStarContainer == null) return;

        for (int i = 0; i < editStarContainer.getChildren().size(); i++) {
            final int index = i + 1;
            if (editStarContainer.getChildren().get(i) instanceof Button b) {
                b.setCursor(javafx.scene.Cursor.HAND);
                b.setOnAction(e -> {
                    noteModifiee = index;
                    actualiserEtoiles();
                });
            }
        }
    }

    /**
     * Met à jour visuellement les étoiles selon la note sélectionnée
     */
    private void actualiserEtoiles() {
        if (editStarContainer == null) return;

        for (int i = 0; i < editStarContainer.getChildren().size(); i++) {
            if (editStarContainer.getChildren().get(i) instanceof Button b) {
                b.setStyle(i < noteModifiee
                        ? "-fx-text-fill: #f1c40f; -fx-background-color: transparent; -fx-font-size: 24; -fx-padding: 0;"
                        : "-fx-text-fill: #bdc3c7; -fx-background-color: transparent; -fx-font-size: 24; -fx-padding: 0;");
            }
        }
    }

    // --- ACTIONS BOUTONS ---

    @FXML
    private void onModifier() {
        try {
            // Désormais feedbackId est reconnu ici
            fs.modifierFeedback(feedbackId, txtAreaComment.getText(), noteModifiee);
            afficherAlerte("Succès", "Votre avis a été mis à jour !", Alert.AlertType.INFORMATION);
        } catch (SQLException e) {
            e.printStackTrace();
            afficherAlerte("Erreur", "Erreur SQL : " + e.getMessage(), Alert.AlertType.ERROR);
        }
    }

    @FXML
    private void onSupprimer() {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION, "Supprimer cet avis ?", ButtonType.YES, ButtonType.NO);
        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.YES) {
                try {
                    // Désormais feedbackId est reconnu ici
                    fs.supprimerFeedback(feedbackId);
                    txtAreaComment.clear();
                    noteModifiee = 0;
                    actualiserEtoiles();
                    afficherAlerte("Supprimé", "Avis supprimé.", Alert.AlertType.INFORMATION);
                } catch (SQLException ex) { ex.printStackTrace(); }
            }
        });
    }

    @FXML
    private void onDownloadCertificat() {
        afficherAlerte("Certificat", "Le téléchargement du certificat pour le participant #" + participantId + " va commencer...", Alert.AlertType.INFORMATION);
    }

    private void afficherAlerte(String titre, String message, Alert.AlertType type) {
        Alert a = new Alert(type);
        a.setTitle(titre);
        a.setHeaderText(null);
        a.setContentText(message);
        a.show();
    }
}