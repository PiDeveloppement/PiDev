package com.example.pidev.controller.questionnaire;

import com.example.pidev.MainController;
import com.example.pidev.model.event.Event;
import com.example.pidev.model.questionnaire.Question;
import com.example.pidev.service.questionnaire.QuestionService;
import com.itextpdf.text.*;
import com.itextpdf.text.pdf.PdfPTable;
import com.itextpdf.text.pdf.PdfWriter;
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

    @FXML private ComboBox<Event> comboEvent, comboEventList;
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

    @FXML
    public void initialize() {
        try {
            if (comboSort != null) {
                comboSort.setItems(FXCollections.observableArrayList(
                        "Points (Croissant)", "Points (Décroissant)", "Texte (A-Z)"
                ));
            }

            List<Event> events = qs.chargerEvenements();

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
                    if (newV != null) chargerQuestionsParEvent(newV.getId());
                });
            }

            if (pieChartQuestions != null) {
                afficherStatistiques();
            }

        } catch (SQLException e) {
            e.printStackTrace();
        }
    }

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

    @FXML
    private void handleSave() {
        try {
            Event ev = comboEvent.getValue();
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
                Question nouvelleQ = new Question(0, ev.getId(), txtTexte.getText(), txtReponse.getText(), points);
                qs.ajouter(nouvelleQ);
                System.out.println("✅ Question ajoutée avec succès !");
            } else {
                // MODIFICATION
                questionEnCours.setIdEvent(ev.getId());
                questionEnCours.setTexte(txtTexte.getText());
                questionEnCours.setReponse(txtReponse.getText());
                questionEnCours.setPoints(points);
                qs.modifier(questionEnCours);
                System.out.println("✅ Question modifiée avec succès !");
                questionEnCours = null; // Reset après modification
            }

            switchToList(); // Retour à la liste

        } catch (SQLException e) {
            e.printStackTrace();
            afficherAlerte("Erreur SQL", "Impossible d'enregistrer : " + e.getMessage());
        } catch (Exception e) {
            e.printStackTrace();
            afficherAlerte("Erreur", "Une erreur inattendue est survenue.");
        }
    }

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

    private void refreshData() throws SQLException {
        if(comboEventList != null && comboEventList.getValue() != null)
            chargerQuestionsParEvent(comboEventList.getValue().getId());
        else {
            toutesLesQuestions = qs.afficherTout();
            questionsFiltrees = new ArrayList<>(toutesLesQuestions);
            afficherPage();
        }
    }

    @FXML
    public void afficherStatistiques() {
        try {
            Map<String, Integer> statsMap = qs.obtenirStats();
            ObservableList<PieChart.Data> pieData = FXCollections.observableArrayList();
            statsMap.forEach((nom, count) -> pieData.add(new PieChart.Data(nom + " (" + count + ")", count)));
            pieChartQuestions.setData(pieData);
        } catch (SQLException e) { e.printStackTrace(); }
    }

    @FXML private void ouvrirPopupStats() { switchToStats(); }

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

    // ==================== MÉTHODES DE NAVIGATION CORRIGÉES ====================

    @FXML
    private void switchToList() {
        changeScene("/com/example/pidev/fxml/questionnaire/list_question.fxml", "📋 LISTE DES QUESTIONS");
    }

    @FXML
    private void switchToForm() {
        changeScene("/com/example/pidev/fxml/questionnaire/form_question.fxml", "⚙️ ÉDITEUR");
    }

    @FXML
    private void switchToStats() {
        changeScene("/com/example/pidev/fxml/questionnaire/Resultat.fxml", "📊 STATISTIQUES");
    }

    /**
     * Méthode générique pour changer de scène avec gestion d'erreur améliorée
     */
    private void changeScene(String fxmlPath, String title) {
        Platform.runLater(() -> {
            try {
                System.out.println("🔍 Tentative de chargement: " + fxmlPath);

                // Vérifier si le chemin commence par /
                String resourcePath = fxmlPath.startsWith("/") ? fxmlPath : "/" + fxmlPath;
                URL resource = getClass().getResource(resourcePath);

                if (resource == null) {
                    System.err.println("❌ FXML non trouvé: " + resourcePath);
                    afficherAlerte("Erreur", "Fichier introuvable: " + resourcePath);
                    return;
                }

                System.out.println("✅ FXML trouvé: " + resource);
                FXMLLoader loader = new FXMLLoader(resource);
                Parent root = loader.load();

                // Récupérer le contrôleur et lui passer le MainController si nécessaire
                Object controller = loader.getController();
                if (controller instanceof QuestionController) {
                    // Pas besoin de setMainController ici car c'est le même type
                }

                if (MainController.getInstance() != null) {
                    MainController.getInstance().setContent(root, title);
                    System.out.println("✅ Navigation vers: " + title);
                } else {
                    System.err.println("❌ MainController instance is null");
                    afficherAlerte("Erreur", "MainController non disponible");
                }

            } catch (IOException e) {
                System.err.println("❌ Erreur chargement: " + fxmlPath);
                e.printStackTrace();
                afficherAlerte("Erreur", "Impossible de charger: " + e.getMessage());
            }
        });
    }

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

    @FXML private void pageSuivante() {
        if ((pageActuelle + 1) * ITEMS_PER_PAGE < questionsFiltrees.size()) {
            pageActuelle++; afficherPage();
        }
    }

    @FXML private void pagePrecedente() {
        if (pageActuelle > 0) {
            pageActuelle--; afficherPage();
        }
    }

    private void remplirChamps(Question q) {
        txtTexte.setText(q.getTexte());
        txtReponse.setText(q.getReponse());
        txtPoints.setText(String.valueOf(q.getPoints()));
        if (comboEvent != null) {
            comboEvent.getItems().stream()
                    .filter(e -> e.getId() == q.getIdEvent())
                    .findFirst()
                    .ifPresent(e -> comboEvent.setValue(e));
        }
    }

    private void afficherAlerte(String t, String m) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(t);
        alert.setHeaderText(null);
        alert.setContentText(m);
        alert.show();
    }
}