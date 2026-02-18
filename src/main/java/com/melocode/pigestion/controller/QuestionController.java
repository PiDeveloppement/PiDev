package com.melocode.pigestion.controller;

import com.itextpdf.text.*;
import com.itextpdf.text.pdf.PdfPCell;
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
import java.net.URL;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;
import java.util.Map;
import java.util.Scanner;
import java.util.stream.Collectors;

public class QuestionController {

    @FXML private ComboBox<Evenement> comboEvent, comboEventList;
    @FXML private ComboBox<String> comboSort;
    @FXML private TextField txtReponse, txtPoints, txtSearch;
    @FXML private TextArea txtTexte;
    @FXML private FlowPane cardsContainer;
    @FXML private Label lblPagination;
    @FXML private Label lblSuggestionIA;
    @FXML private PieChart pieChartQuestions;

    private final QuestionService qs = new QuestionService();
    private static Question questionEnCours = null;

    private List<Question> toutesLesQuestions = new ArrayList<>();
    private List<Question> questionsFiltrees = new ArrayList<>();
    private int pageActuelle = 0;
    private final int ITEMS_PER_PAGE = 6;

    @FXML
    public void initialize() {
        try {
            // Configuration initiale du tri
            if (comboSort != null) {
                comboSort.setItems(FXCollections.observableArrayList(
                        "Points (Croissant)", "Points (Décroissant)", "Texte (A-Z)"
                ));
            }

            List<Evenement> events = qs.chargerEvenements();

            // Mode Formulaire (Ajout/Edition)
            if (comboEvent != null) {
                comboEvent.setItems(FXCollections.observableArrayList(events));
                if (questionEnCours != null) remplirChamps(questionEnCours);

                // Smart Points IA listener
                txtTexte.focusedProperty().addListener((obs, oldVal, newVal) -> {
                    if (!newVal) analyserDifficulteIA(txtTexte.getText());
                });
            }

            // Mode Liste
            if (comboEventList != null) {
                comboEventList.setItems(FXCollections.observableArrayList(events));
                if (!events.isEmpty()) {
                    comboEventList.getSelectionModel().selectFirst();
                    chargerQuestionsParEvent(comboEventList.getSelectionModel().getSelectedItem().getId());
                }
                comboEventList.getSelectionModel().selectedItemProperty().addListener((obs, oldV, newV) -> {
                    if (newV != null) chargerQuestionsParEvent(newV.getId());
                });
            }

            // Mode Statistiques
            if (pieChartQuestions != null) {
                afficherStatistiques();
            }

        } catch (SQLException e) {
            e.printStackTrace();
        }
    }

    // --- MÉTHODE IMPORTANTE : STATISTIQUES ---
    public void afficherStatistiques() {
        try {
            List<Evenement> events = qs.chargerEvenements();
            List<Question> questionsPourStats = new ArrayList<>();

            for (Evenement e : events) {
                questionsPourStats.addAll(qs.afficherParEvenement(e.getId()));
            }

            Map<String, Long> statsMap = questionsPourStats.stream()
                    .collect(Collectors.groupingBy(q -> {
                        return events.stream()
                                .filter(e -> e.getId() == q.getIdEvent())
                                .map(Evenement::getNom)
                                .findFirst()
                                .orElse("Inconnu");
                    }, Collectors.counting()));

            ObservableList<PieChart.Data> pieData = FXCollections.observableArrayList();
            statsMap.forEach((nom, count) -> pieData.add(new PieChart.Data(nom + " (" + count + ")", count)));

            pieChartQuestions.setData(pieData);
        } catch (SQLException e) {
            e.printStackTrace();
        }
    }

    // --- MÉTHODE AJOUTÉE POUR CORRIGER L'ERREUR FXML ---
    @FXML
    private void ouvrirPopupStats() {
        switchToStats();
    }

    // --- MÉTHODE IMPORTANTE : ANALYSE IA ---
    private void analyserDifficulteIA(String texte) {
        if (texte == null || texte.trim().length() < 5) return;

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
            if (lblSuggestionIA != null) {
                lblSuggestionIA.setText("✨ Suggestion IA : " + nivFinal + " (" + ptsFinal + " pts)");
                lblSuggestionIA.setStyle("-fx-text-fill: " + colFinal + "; -fx-font-weight: bold;");
            }
            if (txtPoints != null) txtPoints.setText(String.valueOf(ptsFinal));
        });
    }

    // --- MÉTHODE IMPORTANTE : EXPORT PDF ---
    @FXML
    private void handleExportPDF() {
        Evenement ev = comboEventList.getValue();
        if (ev == null || questionsFiltrees.isEmpty()) {
            afficherAlerte("Export impossible", "Veuillez sélectionner un événement avec des questions.");
            return;
        }

        FileChooser fileChooser = new FileChooser();
        fileChooser.setTitle("Enregistrer le PDF");
        fileChooser.setInitialFileName("Questions_" + ev.getNom().replace(" ", "_") + ".pdf");
        fileChooser.getExtensionFilters().add(new FileChooser.ExtensionFilter("PDF", "*.pdf"));
        File file = fileChooser.showSaveDialog(null);

        if (file != null) {
            try {
                Document document = new Document();
                PdfWriter.getInstance(document, new FileOutputStream(file));
                document.open();

                Font titleFont = FontFactory.getFont(FontFactory.HELVETICA_BOLD, 20);
                Paragraph title = new Paragraph("EventFlow - Liste des Questions\n\n", titleFont);
                title.setAlignment(Element.ALIGN_CENTER);
                document.add(title);
                document.add(new Paragraph("Événement : " + ev.getNom()));
                document.add(new Paragraph(" "));

                PdfPTable table = new PdfPTable(3);
                table.setWidthPercentage(100);
                table.addCell("Question"); table.addCell("Réponse"); table.addCell("Points");

                for (Question q : questionsFiltrees) {
                    table.addCell(q.getTexteQuestion());
                    table.addCell(q.getBonneReponse());
                    table.addCell(String.valueOf(q.getPoints()));
                }

                document.add(table);
                document.close();
                new Alert(Alert.AlertType.INFORMATION, "PDF généré avec succès !").show();
            } catch (Exception e) {
                afficherAlerte("Erreur PDF", e.getMessage());
            }
        }
    }

    // --- MÉTHODE IMPORTANTE : TRADUCTION ---
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
                    labelAtraduire.setStyle("-fx-font-weight: bold; -fx-font-size: 14px; -fx-text-fill: #4f46e5;");
                });
            } catch (Exception e) {
                Platform.runLater(() -> labelAtraduire.setText(texte));
            }
        }).start();
    }

    @FXML
    private void handleSort() {
        String criteria = comboSort.getValue();
        if (criteria == null || questionsFiltrees.isEmpty()) return;
        switch (criteria) {
            case "Points (Croissant)": questionsFiltrees.sort(Comparator.comparingInt(Question::getPoints)); break;
            case "Points (Décroissant)": questionsFiltrees.sort((q1, q2) -> Integer.compare(q2.getPoints(), q1.getPoints())); break;
            case "Texte (A-Z)": questionsFiltrees.sort(Comparator.comparing(q -> q.getTexteQuestion().toLowerCase())); break;
        }
        pageActuelle = 0;
        afficherPage();
    }

    @FXML
    private void handleSearch() {
        if (txtSearch == null) return;
        String query = txtSearch.getText().toLowerCase().trim();
        questionsFiltrees = toutesLesQuestions.stream()
                .filter(q -> q.getTexteQuestion().toLowerCase().contains(query) || q.getBonneReponse().toLowerCase().contains(query))
                .collect(Collectors.toList());
        pageActuelle = 0;
        afficherPage();
    }

    private void chargerQuestionsParEvent(int idEvent) {
        try {
            toutesLesQuestions = qs.afficherParEvenement(idEvent);
            questionsFiltrees = new ArrayList<>(toutesLesQuestions);
            pageActuelle = 0;
            afficherPage();
        } catch (SQLException e) { e.printStackTrace(); }
    }

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

    private VBox createPinterestCard(Question q) {
        VBox card = new VBox(12);
        card.setPrefSize(300, 180);
        card.setStyle("-fx-background-color: white; -fx-background-radius: 20; -fx-padding: 20; -fx-effect: dropshadow(three-pass-box, rgba(0,0,0,0.08), 12, 0, 0, 5);");

        Label lblTxt = new Label(q.getTexteQuestion());
        lblTxt.setStyle("-fx-font-weight: bold; -fx-font-size: 15px; -fx-text-fill: #1e293b;");
        lblTxt.setWrapText(true);
        lblTxt.setMinHeight(50);

        Label lblRep = new Label("💡 " + q.getBonneReponse());
        lblRep.setStyle("-fx-text-fill: #64748b; -fx-font-style: italic;");

        HBox footer = new HBox(10);
        footer.setAlignment(Pos.CENTER_LEFT);

        Label pts = new Label(q.getPoints() + " pts");
        pts.setStyle("-fx-background-color: #e0e7ff; -fx-text-fill: #4338ca; -fx-padding: 4 10; -fx-background-radius: 8; -fx-font-weight: bold;");

        Button btnTranslate = new Button("🌐");
        btnTranslate.setStyle("-fx-background-color: #f1f5f9; -fx-cursor: hand; -fx-background-radius: 8;");
        btnTranslate.setOnAction(e -> traduireTexte(lblTxt));

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Button btnEdit = new Button("✏️");
        btnEdit.setStyle("-fx-background-color: #fef9c3; -fx-cursor: hand; -fx-background-radius: 8;");
        btnEdit.setOnAction(e -> { questionEnCours = q; switchToForm(); });

        Button btnDel = new Button("🗑️");
        btnDel.setStyle("-fx-background-color: #fee2e2; -fx-cursor: hand; -fx-background-radius: 8;");
        btnDel.setOnAction(e -> {
            try {
                qs.supprimer(q.getIdQuestion());
                chargerQuestionsParEvent(q.getIdEvent());
            } catch (SQLException ex) { ex.printStackTrace(); }
        });

        footer.getChildren().addAll(pts, btnTranslate, spacer, btnEdit, btnDel);
        card.getChildren().addAll(lblTxt, lblRep, footer);
        return card;
    }

    @FXML
    private void handleSave() {
        try {
            Evenement ev = comboEvent.getValue();
            if (ev == null || txtTexte.getText().isEmpty() || txtReponse.getText().isEmpty() || txtPoints.getText().isEmpty()) {
                afficherAlerte("Erreur", "Tous les champs sont obligatoires !");
                return;
            }
            if (questionEnCours == null) {
                qs.ajouter(new Question(0, ev.getId(), txtTexte.getText(), txtReponse.getText(), Integer.parseInt(txtPoints.getText())));
            } else {
                questionEnCours.setIdEvent(ev.getId());
                questionEnCours.setTexteQuestion(txtTexte.getText());
                questionEnCours.setBonneReponse(txtReponse.getText());
                questionEnCours.setPoints(Integer.parseInt(txtPoints.getText()));
                qs.modifier(questionEnCours);
                questionEnCours = null;
            }
            switchToList();
        } catch (Exception e) {
            afficherAlerte("Erreur", "Veuillez vérifier le format des points (nombre entier).");
        }
    }

    @FXML private void viderChamps() {
        if(txtTexte != null) txtTexte.clear();
        if(txtReponse != null) txtReponse.clear();
        if(txtPoints != null) txtPoints.clear();
        if(lblSuggestionIA != null) lblSuggestionIA.setText("");
        questionEnCours = null;
    }

    private void remplirChamps(Question q) {
        txtTexte.setText(q.getTexteQuestion());
        txtReponse.setText(q.getBonneReponse());
        txtPoints.setText(String.valueOf(q.getPoints()));
        if(comboEvent != null) {
            comboEvent.getItems().stream()
                    .filter(e -> e.getId() == q.getIdEvent())
                    .findFirst()
                    .ifPresent(e -> comboEvent.setValue(e));
        }
    }

    // --- LOGIQUE DE NAVIGATION ---
    @FXML private void switchToList() { changeScene("/com/melocode/pigestion/fxml/list_question.fxml", "📋 LISTE DES QUESTIONS"); }
    @FXML private void switchToForm() {
        questionEnCours = null;
        changeScene("/com/melocode/pigestion/fxml/form_question.fxml", "⚙️ ÉDITEUR DE QUESTION");
    }
    @FXML private void switchToStats() { changeScene("/com/melocode/pigestion/fxml/stats_question.fxml", "📊 STATISTIQUES"); }

    private void changeScene(String fxmlPath, String title) {
        Platform.runLater(() -> {
            try {
                FXMLLoader loader = new FXMLLoader(getClass().getResource(fxmlPath));
                Parent root = loader.load();
                if (main_layout.getInstance() != null) {
                    main_layout.getInstance().setContent(root, title);
                }
            } catch (IOException e) {
                System.err.println("Erreur de chargement FXML : " + fxmlPath);
                e.printStackTrace();
            }
        });
    }

    @FXML private void pageSuivante() {
        if ((pageActuelle + 1) * ITEMS_PER_PAGE < questionsFiltrees.size()) {
            pageActuelle++;
            afficherPage();
        }
    }

    @FXML private void pagePrecedente() {
        if (pageActuelle > 0) {
            pageActuelle--;
            afficherPage();
        }
    }

    private void afficherAlerte(String t, String m) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle(t);
        alert.setHeaderText(null);
        alert.setContentText(m);
        alert.show();
    }
}