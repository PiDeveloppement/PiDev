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
    private int noteModifiee = 0;
    private final FeedbackService fs = new FeedbackService();

    public void setParticipantId(int id) {
        this.participantId = id;
    }

    public void initData(List<Question> questions, List<String> reponses, String commentaire, int noteInitial) {
        int score = 0;
        vboxCorrection.getChildren().clear();

        for (int i = 0; i < questions.size(); i++) {
            Question q = questions.get(i);
            String repU = (i < reponses.size()) ? reponses.get(i) : "";

            // CORRECTION ICI : Utilisation de getBonneReponse() de ton modèle
            boolean estCorrect = repU.equalsIgnoreCase(q.getBonneReponse());
            if (estCorrect) score++;

            VBox bloc = new VBox(5);
            Label lblQ = new Label((i + 1) + ". " + q.getTexteQuestion());
            lblQ.setStyle("-fx-font-weight: bold;");

            Label lblR = new Label((estCorrect ? "✔ " : "✘ ") + "Votre réponse : " + repU);
            lblR.setTextFill(estCorrect ? Color.GREEN : Color.RED);

            if (!estCorrect) {
                // CORRECTION ICI AUSSI
                Label lblCorrect = new Label("La bonne réponse était : " + q.getBonneReponse());
                lblCorrect.setTextFill(Color.GRAY);
                bloc.getChildren().addAll(lblQ, lblR, lblCorrect);
            } else {
                bloc.getChildren().addAll(lblQ, lblR);
            }
            vboxCorrection.getChildren().add(bloc);
        }

        lblScore.setText("Score: " + score + "/" + questions.size());
        if (score >= (questions.size() / 2.0)) {
            lblStatus.setText("RÉUSSI");
            lblStatus.setTextFill(Color.GREEN);
            btnCertificat.setVisible(true);
        } else {
            lblStatus.setText("ÉCHEC");
            lblStatus.setTextFill(Color.RED);
            btnCertificat.setVisible(false);
        }

        this.noteModifiee = noteInitial;
        txtAreaComment.setText(commentaire);
        setupEditStars();
        actualiserEtoiles();
    }

    private void setupEditStars() {
        for (int i = 0; i < editStarContainer.getChildren().size(); i++) {
            final int index = i + 1;
            if (editStarContainer.getChildren().get(i) instanceof Button b) {
                b.setOnAction(e -> {
                    noteModifiee = index;
                    actualiserEtoiles();
                });
            }
        }
    }

    private void actualiserEtoiles() {
        for (int i = 0; i < editStarContainer.getChildren().size(); i++) {
            if (editStarContainer.getChildren().get(i) instanceof Button b) {
                b.setStyle(i < noteModifiee
                        ? "-fx-text-fill: #f1c40f; -fx-background-color: transparent; -fx-font-size: 20;"
                        : "-fx-text-fill: #bdc3c7; -fx-background-color: transparent; -fx-font-size: 20;");
            }
        }
    }

    @FXML
    private void onModifier() {
        try {
            fs.modifierFeedback(participantId, txtAreaComment.getText(), noteModifiee);
            new Alert(Alert.AlertType.INFORMATION, "Avis mis à jour !").show();
        } catch (SQLException e) {
            e.printStackTrace();
        }
    }

    @FXML
    private void onSupprimer() {
        try {
            fs.supprimerFeedback(participantId);
            new Alert(Alert.AlertType.INFORMATION, "Avis supprimé.").show();
            txtAreaComment.clear();
            noteModifiee = 0;
            actualiserEtoiles();
        } catch (SQLException e) {
            e.printStackTrace();
        }
    }

    @FXML
    private void onDownloadCertificat() {
        System.out.println("Téléchargement pour le participant " + participantId);
    }
}