package com.melocode.pigestion.controller;

import com.melocode.pigestion.model.Evenement;
import com.melocode.pigestion.model.Question;
import com.melocode.pigestion.service.QuestionService;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import java.sql.SQLException;

public class QuestionController {

    @FXML private ComboBox<Evenement> comboEvent;
    @FXML private TextField txtReponse, txtPoints;
    @FXML private TextArea txtTexte;
    @FXML private TableView<Question> tableQuestions;
    @FXML private TableColumn<Question, String> colTexte, colReponse;
    @FXML private TableColumn<Question, Integer> colPoints;
    @FXML private TableColumn<Question, Void> colActions;

    private final QuestionService qs = new QuestionService();
    private Question questionEnCours = null;

    @FXML
    public void initialize() {
        colTexte.setCellValueFactory(new PropertyValueFactory<>("texteQuestion"));
        colReponse.setCellValueFactory(new PropertyValueFactory<>("bonneReponse"));
        colPoints.setCellValueFactory(new PropertyValueFactory<>("points"));

        // Charger les événements
        try {
            comboEvent.setItems(FXCollections.observableArrayList(qs.chargerEvenements()));
        } catch (SQLException e) { e.printStackTrace(); }

        // ÉCOUTEUR : Quand on change d'événement, on filtre le tableau
        comboEvent.getSelectionModel().selectedItemProperty().addListener((obs, oldVal, newVal) -> {
            if (newVal != null) {
                chargerQuestionsParEvent(newVal.getId());
            }
        });

        configurerBoutonsActions();
    }

    private void chargerQuestionsParEvent(int idEvent) {
        try {
            tableQuestions.setItems(FXCollections.observableArrayList(qs.afficherParEvenement(idEvent)));
        } catch (SQLException e) {
            afficherAlerte("Erreur", "Impossible de charger les questions.");
        }
    }

    @FXML
    private void handleSave() {
        try {
            Evenement ev = comboEvent.getValue();
            if (ev == null || txtTexte.getText().isEmpty()) return;

            int idEv = ev.getId();
            if (questionEnCours == null) {
                qs.ajouter(new Question(0, idEv, txtTexte.getText(), txtReponse.getText(), Integer.parseInt(txtPoints.getText())));
            } else {
                questionEnCours.setIdEvent(idEv);
                questionEnCours.setTexteQuestion(txtTexte.getText());
                questionEnCours.setBonneReponse(txtReponse.getText());
                questionEnCours.setPoints(Integer.parseInt(txtPoints.getText()));
                qs.modifier(questionEnCours);
            }
            chargerQuestionsParEvent(idEv); // Rafraîchir le tableau filtré
            viderChampsSaufCombo();
        } catch (Exception e) {
            afficherAlerte("Erreur", "Vérifiez vos données.");
        }
    }

    private void configurerBoutonsActions() {
        colActions.setCellFactory(param -> new TableCell<>() {
            private final Button btnEdit = new Button("Modifier");
            private final Button btnDelete = new Button("Supprimer");
            private final HBox container = new HBox(btnEdit, btnDelete);
            {
                container.setSpacing(10);
                btnEdit.setOnAction(event -> {
                    questionEnCours = getTableView().getItems().get(getIndex());
                    remplirChamps(questionEnCours);
                });
                btnDelete.setOnAction(event -> {
                    Question q = getTableView().getItems().get(getIndex());
                    try {
                        qs.supprimer(q.getIdQuestion());
                        chargerQuestionsParEvent(q.getIdEvent());
                    } catch (SQLException e) {
                        afficherAlerte("Erreur", "Suppression impossible.");
                    }
                });
            }
            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : container);
            }
        });
    }

    private void remplirChamps(Question q) {
        txtTexte.setText(q.getTexteQuestion());
        txtReponse.setText(q.getBonneReponse());
        txtPoints.setText(String.valueOf(q.getPoints()));
    }

    @FXML
    private void viderChampsSaufCombo() {
        txtTexte.clear();
        txtReponse.clear();
        txtPoints.clear();
        questionEnCours = null;
    }

    private void afficherAlerte(String t, String m) {
        Alert a = new Alert(Alert.AlertType.ERROR);
        a.setTitle(t); a.setContentText(m); a.show();
    }
}