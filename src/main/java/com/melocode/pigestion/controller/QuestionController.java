package com.melocode.pigestion.controller;

import com.itextpdf.text.*;
import com.itextpdf.text.pdf.PdfPTable;
import com.itextpdf.text.pdf.PdfWriter;
import com.melocode.pigestion.main_layout;
import com.melocode.pigestion.model.Evenement;
import com.melocode.pigestion.model.Question;
import com.melocode.pigestion.service.QuestionService;
import javafx.application.Platform;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.chart.PieChart;
import javafx.scene.control.*;
import javafx.scene.layout.*;
import javafx.stage.FileChooser;

import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.net.URL;//
import java.net.URLEncoder;//
import java.nio.charset.StandardCharsets;//
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;
import java.util.Map;
import java.util.Scanner;//
import java.util.stream.Collectors;

public class QuestionController {

    @FXML private ComboBox<Evenement> comboEvent, comboEventList;
    @FXML private ComboBox<String> comboSort;
    @FXML private TextField txtReponse, txtPoints, txtSearch;
    @FXML private TextArea txtTexte;
    @FXML private FlowPane cardsContainer;
    @FXML private Label lblPagination, lblSuggestionIA;
    @FXML private PieChart pieChartQuestions;

    private final QuestionService qs = new QuestionService();
    private static Question questionEnCours = null;
    private List<Question> toutesLesQuestions = new ArrayList<>();
    private List<Question> questionsFiltrees = new ArrayList<>();
    private int pageActuelle = 0;
    private final int ITEMS_PER_PAGE = 6;
// Initialise l’interface : charge les événements, les questions, la pagination, les stats et les listeners

    @FXML
    public void initialize() {
        try {
            if (comboSort != null) {
                comboSort.setItems(FXCollections.observableArrayList(
                        "Points (Croissant)", "Points (Décroissant)", "Texte (A-Z)"
                ));
            }

            List<Evenement> events = qs.chargerEvenements();

            if (comboEvent != null) {
                comboEvent.setItems(FXCollections.observableArrayList(events));
                if (questionEnCours != null) {
                    remplirChamps(questionEnCours);
                }

                txtTexte.focusedProperty().addListener((obs, oldVal, newVal) -> {
                    if (!newVal) analyserDifficulteIA(txtTexte.getText());
                });
            }

            if (comboEventList != null) {
                comboEventList.setItems(FXCollections.observableArrayList(events));
                toutesLesQuestions = qs.afficherTout();
                questionsFiltrees = new ArrayList<>(toutesLesQuestions);
                afficherPage();

                comboEventList.getSelectionModel().selectedItemProperty().addListener((obs, oldV, newV) -> {
                    if (newV != null) chargerQuestionsParEvent(newV.getIdEvent());
                });
            }

            if (pieChartQuestions != null) {
                afficherStatistiques();
            }

        } catch (SQLException e) {
            e.printStackTrace();
        }
    }
    // Analyse automatiquement la difficulté d’une question et propose un niveau + points
    private void analyserDifficulteIA(String texte) {
        if (texte == null || texte.trim().length() < 5 || lblSuggestionIA == null) return;

        String t = texte.toLowerCase();
        int points = 5;
        String niveau = "Facile";
        String color = "#22c55e";

        if (t.contains("pourquoi") || t.contains("comment") || t.length() > 50) {
            points = 15; niveau = "Moyenne"; color = "#f59e0b";
        }
        if (t.contains("expliquer") || t.contains("comparer") || t.contains("décrire") || t.length() > 100) {
            points = 25; niveau = "Difficile"; color = "#ef4444";
        }

        final int ptsFinal = points;
        final String nivFinal = niveau;
        final String colFinal = color;

        Platform.runLater(() -> {
            lblSuggestionIA.setText("✨ Suggestion IA : " + nivFinal + " (" + ptsFinal + " pts)");
            lblSuggestionIA.setStyle("-fx-text-fill: " + colFinal + "; -fx-font-weight: bold;");
            if (txtPoints != null) txtPoints.setText(String.valueOf(ptsFinal));
        });
    }
    // Ajoute une nouvelle question ou modifie une question existante
    @FXML
    private void handleSave() {
        try {
            Evenement ev = comboEvent.getValue();
            // Vérification rigoureuse des champs
            if (ev == null || txtTexte.getText().trim().isEmpty() || txtReponse.getText().trim().isEmpty()) {
                afficherAlerte("Champs manquants", "Veuillez sélectionner un événement et remplir l'énoncé et la réponse.");
                return;
            }

            // Conversion sécurisée des points
            int points;
            try {
                points = Integer.parseInt(txtPoints.getText().trim());
            } catch (NumberFormatException e) {
                afficherAlerte("Format invalide", "Le champ 'Points' doit être un nombre entier.");
                return;
            }

            if (questionEnCours == null) {
                // AJOUT
                Question nouvelleQ = new Question(0, ev.getIdEvent(), txtTexte.getText(), txtReponse.getText(), points);
                qs.ajouter(nouvelleQ);
                System.out.println("✅ Question ajoutée avec succès !");
            } else {
                // MODIFICATION
                questionEnCours.setIdEvent(ev.getIdEvent());
                questionEnCours.setTexte(txtTexte.getText());
                questionEnCours.setReponse(txtReponse.getText());
                questionEnCours.setPoints(points);
                qs.modifier(questionEnCours);
                System.out.println("✅ Question modifiée avec succès !");
                questionEnCours = null; // Reset après modification
            }

            switchToList(); // Retour à la liste

        } catch (SQLException e) {
            e.printStackTrace(); // Affiche l'erreur SQL exacte dans la console
            afficherAlerte("Erreur SQL", "Impossible d'enregistrer : " + e.getMessage());
        } catch (Exception e) {
            e.printStackTrace();
            afficherAlerte("Erreur", "Une erreur inattendue est survenue.");
        }
    }
    // Recherche des questions par mot-clé (texte ou réponse)
    @FXML
    private void handleSearch() {
        if (txtSearch == null) return;
        String query = txtSearch.getText().toLowerCase().trim();
        questionsFiltrees = toutesLesQuestions.stream()
                .filter(q -> q.getTexte().toLowerCase().contains(query) || q.getReponse().toLowerCase().contains(query))
                .collect(Collectors.toList());
        pageActuelle = 0;
        afficherPage();
    }
    // Trie les questions selon le critère choisi (points ou texte)
    @FXML
    private void handleSort() {
        String criteria = comboSort.getValue();
        if (criteria == null || questionsFiltrees.isEmpty()) return;
        switch (criteria) {
            case "Points (Croissant)": questionsFiltrees.sort(Comparator.comparingInt(Question::getPoints)); break;
            case "Points (Décroissant)": questionsFiltrees.sort((q1, q2) -> Integer.compare(q2.getPoints(), q1.getPoints())); break;
            case "Texte (A-Z)": questionsFiltrees.sort(Comparator.comparing(q -> q.getTexte().toLowerCase())); break;
        }
        pageActuelle = 0;
        afficherPage();
    }
    // Charge uniquement les questions liées à un événement précis
    private void chargerQuestionsParEvent(int idEvent) {
        try {
            toutesLesQuestions = qs.afficherParEvenement(idEvent);
            questionsFiltrees = new ArrayList<>(toutesLesQuestions);
            pageActuelle = 0;
            afficherPage();
        } catch (SQLException e) { e.printStackTrace(); }
    }
    // Affiche les questions page par page (pagination)
    private void afficherPage() {
        if (cardsContainer == null) return;
        cardsContainer.getChildren().clear();
        int total = questionsFiltrees.size();
        int start = pageActuelle * ITEMS_PER_PAGE;
        int end = Math.min(start + ITEMS_PER_PAGE, total);

        for (int i = start; i < end; i++) {
            cardsContainer.getChildren().add(createPinterestCard(questionsFiltrees.get(i)));
        }

        if (lblPagination != null) {
            int totalPages = (int) Math.ceil((double) total / ITEMS_PER_PAGE);
            lblPagination.setText("Page " + (totalPages == 0 ? 0 : pageActuelle + 1) + " / " + totalPages);
        }
    }
    // Crée une carte graphique (UI) pour afficher une question
    private VBox createPinterestCard(Question q) {
        VBox card = new VBox(12);
        card.setPrefSize(300, 180);
        card.setStyle("-fx-background-color: white; -fx-background-radius: 20; -fx-padding: 20; -fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.08), 12, 0, 0, 5);");

        Label lblTxt = new Label(q.getTexte());
        lblTxt.setStyle("-fx-font-weight: bold; -fx-font-size: 14px; -fx-text-fill: #1e293b;");
        lblTxt.setWrapText(true);
        lblTxt.setMinHeight(50);

        HBox footer = new HBox(10);
        footer.setAlignment(Pos.CENTER_LEFT);

        Label pts = new Label(q.getPoints() + " pts");
        pts.setStyle("-fx-background-color: #e0e7ff; -fx-text-fill: #4338ca; -fx-padding: 4 10; -fx-background-radius: 8; -fx-font-weight: bold;");

        Button btnTranslate = new Button("🌐");
        btnTranslate.setOnAction(e -> traduireTexte(lblTxt));

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Button btnEdit = new Button("✏️");
        btnEdit.setOnAction(e -> {
            questionEnCours = q;
            switchToForm();
        });

        Button btnDel = new Button("🗑️");
        btnDel.setStyle("-fx-background-color: #fee2e2;");
        btnDel.setOnAction(e -> {
            try {
                qs.supprimer(q.getIdQuestion());
                refreshData();
            } catch (SQLException ex) { ex.printStackTrace(); }
        });

        footer.getChildren().addAll(pts, btnTranslate, spacer, btnEdit, btnDel);
        card.getChildren().addAll(lblTxt, new Label("💡 " + q.getReponse()), footer);
        return card;
    }
    // Recharge les données depuis la base de données
    private void refreshData() throws SQLException {
        if(comboEventList != null && comboEventList.getValue() != null)
            chargerQuestionsParEvent(comboEventList.getValue().getIdEvent());
        else {
            toutesLesQuestions = qs.afficherTout();
            questionsFiltrees = new ArrayList<>(toutesLesQuestions);
            afficherPage();
        }
    }

    @FXML
    // Affiche les statistiques des questions dans un graphique circulaire
    public void afficherStatistiques() {
        try {
            Map<String, Integer> statsMap = qs.obtenirStats();
            ObservableList<PieChart.Data> pieData = FXCollections.observableArrayList();
            statsMap.forEach((nom, count) -> pieData.add(new PieChart.Data(nom + " (" + count + ")", count)));
            pieChartQuestions.setData(pieData);
        } catch (SQLException e) { e.printStackTrace(); }
    }
    // Ouvre la vue/statistiques des questions
    @FXML private void ouvrirPopupStats() { switchToStats(); }
// Traduit le texte d’une question du français vers l’anglais via une API web

    private void traduireTexte(Label labelAtraduire) {
        String texte = labelAtraduire.getText();
        labelAtraduire.setText("⌛...");
        new Thread(() -> {
            try {
                String encodedText = URLEncoder.encode(texte, StandardCharsets.UTF_8.toString());
                String urlStr = "https://api.mymemory.translated.net/get?q=" + encodedText + "&langpair=fr|en";
                URL url = new URL(urlStr);
                Scanner s = new Scanner(url.openStream(), "UTF-8").useDelimiter("\\A");
                String response = s.hasNext() ? s.next() : "";
                String translatedText = response.split("\"translatedText\":\"")[1].split("\"")[0];
                Platform.runLater(() -> {
                    labelAtraduire.setText(translatedText);
                    labelAtraduire.setStyle("-fx-text-fill: #4f46e5; -fx-font-weight: bold;");
                });
            } catch (Exception e) {
                Platform.runLater(() -> labelAtraduire.setText(texte));
            }
        }).start();
    }
// Exporte la liste des questions dans un fichier PDF

    @FXML
    private void handleExportPDF() {
        if (questionsFiltrees.isEmpty()) {
            afficherAlerte("Export impossible", "La liste est vide.");
            return;
        }

        FileChooser fileChooser = new FileChooser();
        fileChooser.setInitialFileName("Questions_Export.pdf");
        File file = fileChooser.showSaveDialog(null);

        if (file != null) {
            try {
                Document document = new Document();
                PdfWriter.getInstance(document, new FileOutputStream(file));
                document.open();
                document.add(new Paragraph("LISTE DES QUESTIONS\n\n"));
                PdfPTable table = new PdfPTable(3);
                table.addCell("Question"); table.addCell("Réponse"); table.addCell("Points");
                for (Question q : questionsFiltrees) {
                    table.addCell(q.getTexte());
                    table.addCell(q.getReponse());
                    table.addCell(String.valueOf(q.getPoints()));
                }
                document.add(table);
                document.close();
                afficherAlerte("Succès", "PDF généré !");
            } catch (Exception e) { e.printStackTrace(); }
        }
    }
// Change la vue vers la liste des questions
    @FXML private void switchToList() { changeScene("/com/melocode/pigestion/fxml/list_question.fxml", "📋 LISTE DES QUESTIONS"); }
    // Change la vue vers le formulaire d’ajout/modification
    @FXML private void switchToForm() { changeScene("/com/melocode/pigestion/fxml/form_question.fxml", "⚙️ ÉDITEUR"); }
    // Change la vue vers la page des statistiques
    @FXML private void switchToStats() { changeScene("/com/melocode/pigestion/fxml/stats_question.fxml", "📊 STATISTIQUES"); }
// Change dynamiquement le contenu principal de l’interface

    private void changeScene(String fxmlPath, String title) {
        Platform.runLater(() -> {
            try {
                FXMLLoader loader = new FXMLLoader(getClass().getResource(fxmlPath));
                Parent root = loader.load();
                if (main_layout.getInstance() != null) {
                    main_layout.getInstance().setContent(root, title);
                }
            } catch (IOException e) { e.printStackTrace(); }
        });
    }
// Vide les champs du formulaire sans toucher à l’événement sélectionné

    @FXML
    private void viderChampsSaufCombo() {
        if(txtTexte != null) txtTexte.clear();
        if(txtReponse != null) txtReponse.clear();
        if(txtPoints != null) txtPoints.clear();
        if(lblSuggestionIA != null) lblSuggestionIA.setText("");
        // On ne touche pas à comboEvent pour permettre l'ajout rapide
        // de plusieurs questions pour le même événement.
        questionEnCours = null;
    }
// Passe à la page suivante de la pagination

    @FXML private void pageSuivante() {
        if ((pageActuelle + 1) * ITEMS_PER_PAGE < questionsFiltrees.size()) {
            pageActuelle++; afficherPage();
        }
    }
// Revient à la page précédente de la pagination

    @FXML private void pagePrecedente() {
        if (pageActuelle > 0) {
            pageActuelle--; afficherPage();
        }
    }
// Remplit le formulaire avec les données d’une question existante

    private void remplirChamps(Question q) {
        txtTexte.setText(q.getTexte());
        txtReponse.setText(q.getReponse());
        txtPoints.setText(String.valueOf(q.getPoints()));
        if (comboEvent != null) {
            comboEvent.getItems().stream()
                    .filter(e -> e.getIdEvent() == q.getIdEvent())
                    .findFirst()
                    .ifPresent(e -> comboEvent.setValue(e));
        }
    }
// Affiche une fenêtre d’alerte avec un message

    private void afficherAlerte(String t, String m) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(t);
        alert.setHeaderText(null);
        alert.setContentText(m);
        alert.show();
    }
}