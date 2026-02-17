package com.example.pidev.controller.event;

import com.example.pidev.HelloController;
import com.example.pidev.model.event.EventCategory;
import com.example.pidev.service.event.EventCategoryService;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.scene.paint.Color;
import javafx.scene.shape.Circle;
import javafx.scene.shape.SVGPath;
import javafx.util.Duration;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.Comparator;
import java.util.List;
import java.util.Locale;
import java.util.stream.Collectors;

/**
 * Controller pour la liste des catégories - Version simplifiée avec diagnostic
 * @author Ons Abdesslem
 */
public class CategoryListController {

    // ========== TABLEAU ==========
    @FXML private TableView<EventCategory> categoryTable;
    @FXML private TableColumn<EventCategory, EventCategory> categoryCol;
    @FXML private TableColumn<EventCategory, String> descriptionCol;
    @FXML private TableColumn<EventCategory, String> colorCol;
    @FXML private TableColumn<EventCategory, Boolean> statusCol;
    @FXML private TableColumn<EventCategory, Void> actionsCol;

    // ========== FILTRES ==========
    @FXML private TextField searchField;
    @FXML private ComboBox<String> statusFilter;
    @FXML private ComboBox<String> colorFilter;
    @FXML private ComboBox<String> sortOrder;

    // ========== KPI ==========
    @FXML private Label totalLabel;
    @FXML private Label eventsLabel;
    @FXML private Label resultLabel;

    // ========== NAVBAR ==========
    @FXML private Label dateLabel;
    @FXML private Label timeLabel;

    // ========== BOUTONS ==========
    @FXML private Button addBtn;

    // ========== PAGINATION ==========
    @FXML private HBox paginationContainer;
    @FXML private Button prevBtn;
    @FXML private Label pageInfoLabel;

    private EventCategoryService categoryService;
    private HelloController helloController;
    private List<EventCategory> allCategories;
    private List<EventCategory> filteredCategories; // Liste filtrée pour la pagination

    // Variables de pagination
    private int currentPage = 1;
    private int itemsPerPage = 10; // 10 catégories par page
    private int totalPages = 1;


    @FXML
    public void initialize() {
        System.out.println("✅ CategoryListController initialisé");

        categoryService = new EventCategoryService();

        setupFilters();
        setupTableColumns();
        loadCategories();

        // ========== DATE/HEURE TEMPS RÉEL ==========
        updateDateTime();
        Timeline clock = new Timeline(
                new KeyFrame(Duration.ZERO, e -> updateDateTime()),
                new KeyFrame(Duration.seconds(1))
        );
        clock.setCycleCount(Timeline.INDEFINITE);
        clock.play();
    }

    private void updateDateTime() {
        LocalDateTime now = LocalDateTime.now();
        DateTimeFormatter dateFormatter = DateTimeFormatter.ofPattern("EEEE dd MMMM yyyy", Locale.FRENCH);
        DateTimeFormatter timeFormatter = DateTimeFormatter.ofPattern("HH:mm:ss");

        if (dateLabel != null) {
            String dateText = now.format(dateFormatter);
            dateLabel.setText(dateText.substring(0, 1).toUpperCase() + dateText.substring(1));
        }
        if (timeLabel != null) {
            timeLabel.setText(now.format(timeFormatter));
        }
    }

    public void setHelloController(HelloController helloController) {
        this.helloController = helloController;
    }

    /**
     * Configuration des filtres avec placeholders intégrés comme premier item
     */
    private void setupFilters() {
        // ============ RECHERCHE ============
        searchField.setPromptText("🔍 Rechercher une catégorie...");
        searchField.textProperty().addListener((obs, old, newVal) -> applyFilters());

        // ============ FILTRE STATUT ============
        statusFilter.getItems().addAll("-- Tous les statuts --", "Actif", "Inactif");
        statusFilter.setValue("-- Tous les statuts --"); // Sélectionner le placeholder par défaut
        statusFilter.valueProperty().addListener((obs, old, newVal) -> applyFilters());

        // Style pour le dropdown
        statusFilter.setCellFactory(param -> new ListCell<String>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setText(null);
                } else {
                    setText(item);
                    if (item.startsWith("--")) {
                        setStyle("-fx-text-fill: #adb5bd; -fx-font-style: italic;");
                    } else {
                        setStyle("-fx-text-fill: #495057;");
                    }
                }
            }
        });

        // Style pour le bouton (ce qui s'affiche quand fermé)
        statusFilter.setButtonCell(new ListCell<String>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setText(null);
                } else {
                    setText(item);
                    if (item.startsWith("--")) {
                        setStyle("-fx-text-fill: #adb5bd; -fx-font-style: italic;");
                    } else {
                        setStyle("-fx-text-fill: #495057;");
                    }
                }
            }
        });

        // ============ FILTRE COULEUR ============
        colorFilter.getItems().add("-- Toutes les couleurs --");
        colorFilter.setValue("-- Toutes les couleurs --");
        colorFilter.valueProperty().addListener((obs, old, newVal) -> applyFilters());

        // ============ TRI (ORDRE) ============
        sortOrder.getItems().addAll("-- Ordre --", "A → Z", "Z → A", "Plus récents", "Plus anciens");
        sortOrder.setValue("-- Ordre --");
        sortOrder.valueProperty().addListener((obs, old, newVal) -> applyFilters());

        // Style pour l'ordre
        sortOrder.setCellFactory(param -> new ListCell<String>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setText(null);
                } else {
                    setText(item);
                    if (item.startsWith("--")) {
                        setStyle("-fx-text-fill: #adb5bd; -fx-font-style: italic;");
                    } else {
                        setStyle("-fx-text-fill: #495057;");
                    }
                }
            }
        });

        sortOrder.setButtonCell(new ListCell<String>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setText(null);
                } else {
                    setText(item);
                    if (item.startsWith("--")) {
                        setStyle("-fx-text-fill: #adb5bd; -fx-font-style: italic;");
                    } else {
                        setStyle("-fx-text-fill: #495057;");
                    }
                }
            }
        });

        System.out.println("✅ Filtres configurés avec placeholders intégrés");
    }

    private void setupTableColumns() {
        // Catégorie avec icône
        categoryCol.setCellValueFactory(param ->
                new javafx.beans.property.SimpleObjectProperty<>(param.getValue())
        );
        categoryCol.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(EventCategory cat, boolean empty) {
                super.updateItem(cat, empty);
                if (empty || cat == null) {
                    setGraphic(null);
                } else {
                    Label icon = new Label(cat.getIcon() != null ? cat.getIcon() : "📌");
                    icon.setStyle("-fx-font-size: 22px;");
                    Label name = new Label(cat.getName());
                    name.setStyle("-fx-font-size: 14px; -fx-font-weight: 600; -fx-text-fill: #2c3e50;");
                    HBox box = new HBox(10, icon, name);
                    box.setAlignment(Pos.CENTER_LEFT);
                    setGraphic(box);
                }
            }
        });

        // Description
        descriptionCol.setCellValueFactory(param ->
                new javafx.beans.property.SimpleStringProperty(
                        param.getValue().getDescription() != null ? param.getValue().getDescription() : ""
                )
        );
        descriptionCol.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(String desc, boolean empty) {
                super.updateItem(desc, empty);
                if (empty || desc == null || desc.isEmpty()) {
                    setText(null);
                } else {
                    setText(desc);
                    setWrapText(true);
                    setStyle("-fx-text-fill: #6c757d; -fx-font-size: 13px; -fx-padding: 8 10;");
                }
            }
        });

        // Couleur avec cercle
        colorCol.setCellValueFactory(param ->
                new javafx.beans.property.SimpleStringProperty(param.getValue().getColor())
        );
        colorCol.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(String colorHex, boolean empty) {
                super.updateItem(colorHex, empty);
                if (empty || colorHex == null) {
                    setGraphic(null);
                } else {
                    Circle circle = new Circle(16);
                    try {
                        circle.setFill(Color.web(colorHex));
                        circle.setStroke(Color.web("#dee2e6"));
                        circle.setStrokeWidth(2);
                    } catch (Exception e) {
                        circle.setFill(Color.GRAY);
                    }
                    Label hex = new Label(colorHex);
                    hex.setStyle("-fx-font-size: 12px; -fx-text-fill: #495057; -fx-font-family: monospace;");
                    HBox box = new HBox(10, circle, hex);
                    box.setAlignment(Pos.CENTER);
                    setGraphic(box);
                }
            }
        });

        // Statut avec badge
        statusCol.setCellValueFactory(param ->
                new javafx.beans.property.SimpleObjectProperty<>(param.getValue().isActive())
        );
        statusCol.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(Boolean active, boolean empty) {
                super.updateItem(active, empty);
                if (empty || active == null) {
                    setGraphic(null);
                } else {
                    Label badge = new Label(active ? "Actif" : "Inactif");
                    badge.getStyleClass().add("status-badge");
                    badge.getStyleClass().add(active ? "status-badge-active" : "status-badge-inactive");
                    setGraphic(badge);
                }
            }
        });

        // Actions (voir, modifier, supprimer)
        actionsCol.setCellFactory(col -> new TableCell<>() {
            private final Button viewBtn = createIconButton("eye", "#17a2b8");
            private final Button editBtn = createIconButton("edit", "#0d6efd");
            private final Button deleteBtn = createIconButton("trash", "#dc3545");
            private final HBox container = new HBox(8, viewBtn, editBtn, deleteBtn);
            {
                container.setAlignment(Pos.CENTER);
            }
            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                if (empty) {
                    setGraphic(null);
                } else {
                    EventCategory category = getTableView().getItems().get(getIndex());
                    viewBtn.setOnAction(e -> handleView(category));
                    editBtn.setOnAction(e -> handleEdit(category));
                    deleteBtn.setOnAction(e -> handleDelete(category));
                    setGraphic(container);
                }
            }
        });
    }

    private Button createIconButton(String iconType, String color) {
        Button btn = new Button();
        btn.setMinSize(38, 38);
        btn.setMaxSize(38, 38);
        btn.getStyleClass().add("action-btn-icon");
        SVGPath icon = new SVGPath();
        switch (iconType) {
            case "eye":
                icon.setContent("M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z");
                break;
            case "edit":
                icon.setContent("M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z");
                break;
            case "trash":
                icon.setContent("M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z");
                break;
        }
        icon.setFill(Color.WHITE);
        icon.setScaleX(0.7);
        icon.setScaleY(0.7);
        btn.setGraphic(icon);
        btn.setStyle("-fx-background-color: " + color + "; -fx-background-radius: 6;");
        return btn;
    }

    private void loadCategories() {
        try {
            allCategories = categoryService.getAllCategoriesWithCount();
            populateColorFilter();
            applyFilters();
            updateStatistics();
            System.out.println("✅ " + allCategories.size() + " catégories chargées");
        } catch (Exception e) {
            System.err.println("❌ Erreur: " + e.getMessage());
            showError("Erreur", "Impossible de charger les catégories");
        }
    }

    private void populateColorFilter() {
        List<String> colors = allCategories.stream()
                .map(EventCategory::getColor)
                .filter(color -> color != null && !color.isEmpty())
                .distinct()
                .sorted()
                .collect(Collectors.toList());

        String currentValue = colorFilter.getValue();
        colorFilter.getItems().clear();
        colorFilter.getItems().add("-- Toutes les couleurs --");
        for (String colorHex : colors) {
            colorFilter.getItems().add(getColorName(colorHex));
        }

        // Restaurer la valeur ou mettre le placeholder par défaut
        if (currentValue != null && colorFilter.getItems().contains(currentValue)) {
            colorFilter.setValue(currentValue);
        } else {
            colorFilter.setValue("-- Toutes les couleurs --");
        }

        // Configuration du CellFactory pour le dropdown avec cercles de couleur
        colorFilter.setCellFactory(param -> new ListCell<String>() {
            private final Circle colorCircle = new Circle(8);
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setGraphic(null);
                    setText(null);
                } else if (item.startsWith("--")) {
                    // Placeholder en gris italique
                    setGraphic(null);
                    setText(item);
                    setStyle("-fx-text-fill: #adb5bd; -fx-font-style: italic;");
                } else {
                    // Couleur normale avec cercle
                    String colorHex = extractColorHex(item);
                    if (colorHex != null) {
                        try {
                            colorCircle.setFill(Color.web(colorHex));
                            colorCircle.setStroke(Color.web("#dee2e6"));
                            colorCircle.setStrokeWidth(1.5);
                        } catch (Exception e) {
                            colorCircle.setFill(Color.GRAY);
                        }
                        HBox box = new HBox(10, colorCircle, new Label(item));
                        box.setAlignment(Pos.CENTER_LEFT);
                        setGraphic(box);
                        setText(null);
                    } else {
                        setGraphic(null);
                        setText(item);
                    }
                    setStyle("-fx-text-fill: #495057;");
                }
            }
        });

        // Configuration du ButtonCell pour l'affichage dans le ComboBox fermé
        colorFilter.setButtonCell(new ListCell<String>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setText(null);
                } else {
                    setText(item);
                    if (item.startsWith("--")) {
                        setStyle("-fx-text-fill: #adb5bd; -fx-font-style: italic;");
                    } else {
                        setStyle("-fx-text-fill: #495057;");
                    }
                }
            }
        });

        System.out.println("✅ Filtre couleur mis à jour avec " + (colors.size() + 1) + " options");
    }

    private String extractColorHex(String colorText) {
        for (EventCategory cat : allCategories) {
            if (cat.getColor() != null && colorText.contains(cat.getColor())) {
                return cat.getColor();
            }
        }
        return null;
    }

    private String getColorName(String hex) {
        if (hex == null) return "Inconnue";
        switch (hex.toUpperCase()) {
            case "#FF9800": return "Orange (" + hex + ")";
            case "#4CAF50": return "Vert (" + hex + ")";
            case "#2196F3":
            case "#1976D2": return "Bleu (" + hex + ")";
            case "#9C27B0": return "Violet (" + hex + ")";
            case "#F44336": return "Rouge (" + hex + ")";
            case "#E91E63": return "Rose (" + hex + ")";
            case "#FFEB3B": return "Jaune (" + hex + ")";
            case "#795548": return "Marron (" + hex + ")";
            case "#607D8B": return "Gris (" + hex + ")";
            default: return hex;
        }
    }

    private void updateStatistics() {
        if (allCategories == null) return;
        int total = allCategories.size();
        int events = allCategories.stream().mapToInt(EventCategory::getEventCount).sum();
        totalLabel.setText(String.valueOf(total));
        eventsLabel.setText(String.valueOf(events));
    }

    @FXML
    private void handleFilter() {
        applyFilters();
    }

    private void applyFilters() {
        if (allCategories == null) return;
        String searchText = searchField.getText().toLowerCase().trim();
        String status = statusFilter.getValue();
        String color = colorFilter.getValue();
        String sort = sortOrder.getValue();

        List<EventCategory> filtered = allCategories.stream()
                .filter(cat -> {
                    boolean matchSearch = searchText.isEmpty() || cat.getName().toLowerCase().contains(searchText);

                    // Filtre statut : ignorer si c'est le placeholder
                    boolean matchStatus = status == null || status.startsWith("--") ||
                            (status.equals("Actif") && cat.isActive()) ||
                            (status.equals("Inactif") && !cat.isActive());

                    // Filtre couleur : ignorer si c'est le placeholder
                    boolean matchColor = color == null || color.startsWith("--") ||
                            (cat.getColor() != null && color.contains(cat.getColor()));

                    return matchSearch && matchStatus && matchColor;
                })
                .collect(Collectors.toList());

        // Tri : ignorer si c'est le placeholder
        if (sort != null && !sort.startsWith("--")) {
            switch (sort) {
                case "A → Z": filtered.sort(Comparator.comparing(EventCategory::getName)); break;
                case "Z → A": filtered.sort(Comparator.comparing(EventCategory::getName).reversed()); break;
                case "Plus récents": filtered.sort(Comparator.comparing(EventCategory::getCreatedAt).reversed()); break;
                case "Plus anciens": filtered.sort(Comparator.comparing(EventCategory::getCreatedAt)); break;
            }
        } else {
            filtered.sort(Comparator.comparing(EventCategory::getName));
        }

        // Stocker les résultats filtrés pour la pagination
        filteredCategories = filtered;

        // Réinitialiser à la page 1 après un nouveau filtrage
        currentPage = 1;

        // Mettre à jour le label de résultats
        resultLabel.setText(filtered.size() + " résultat(s) trouvé(s)");

        // Configurer et afficher la pagination
        setupPagination();
    }

    @FXML
    private void handleAdd() {
        System.out.println("[CategoryList] handleAdd() appelé");
        if (helloController == null) {
            Alert alert = new Alert(Alert.AlertType.ERROR);
            alert.setTitle("Erreur de navigation");
            alert.setHeaderText("Impossible d'ouvrir le formulaire");
            alert.showAndWait();
            return;
        }
        helloController.showCategoryForm(null);
    }

    private void handleView(EventCategory category) {
        Alert details = new Alert(Alert.AlertType.INFORMATION);
        details.setTitle("Détails");
        details.setHeaderText(category.getDisplayName());
        details.setContentText(String.format(
                "Description: %s\n\nCouleur: %s\n\nStatut: %s\n\nÉvénements: %d",
                category.getDescription(), category.getColor(),
                category.isActive() ? "✅ Actif" : "❌ Inactif",
                category.getEventCount()
        ));
        details.showAndWait();
    }

    private void handleEdit(EventCategory category) {
        if (helloController != null) {
            helloController.showCategoryForm(category);
        }
    }

    private void handleDelete(EventCategory category) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Supprimer " + category.getName() + " ?");
        confirm.setContentText("Cette action est irréversible.");
        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                if (categoryService.deleteCategory(category.getId())) {
                    showSuccess("Succès", "Catégorie supprimée !");
                    loadCategories();
                } else {
                    showError("Erreur", "Impossible de supprimer");
                }
            }
        });
    }

    @FXML
    private void handleRefresh() {
        loadCategories();
    }

    private void showError(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle(title);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private void showSuccess(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setContentText(message);
        alert.showAndWait();
    }

    // ===================== PAGINATION =====================

    /**
     * Configure la pagination dynamique
     */
    private void setupPagination() {
        if (filteredCategories == null || filteredCategories.isEmpty()) {
            totalPages = 1;
            currentPage = 1;
            updatePaginationUI();
            return;
        }

        totalPages = (int) Math.ceil((double) filteredCategories.size() / itemsPerPage);
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
        updatePaginationUI();
        displayCurrentPage();
    }

    /**
     * Affiche les items de la page actuelle
     */
    private void displayCurrentPage() {
        if (filteredCategories == null || filteredCategories.isEmpty()) {
            categoryTable.getItems().clear();
            return;
        }

        int fromIndex = (currentPage - 1) * itemsPerPage;
        int toIndex = Math.min(fromIndex + itemsPerPage, filteredCategories.size());

        List<EventCategory> pageItems = filteredCategories.subList(fromIndex, toIndex);
        categoryTable.getItems().clear();
        categoryTable.getItems().addAll(pageItems);
    }

    /**
     * Met à jour l'interface de pagination (boutons + label)
     */
    private void updatePaginationUI() {
        // Mettre à jour le label d'info
        pageInfoLabel.setText("Page " + currentPage + " sur " + totalPages);

        // Nettoyer les boutons existants (sauf "Précédent")
        paginationContainer.getChildren().clear();

        // Bouton Précédent
        prevBtn.setDisable(currentPage == 1);
        paginationContainer.getChildren().add(prevBtn);

        // Générer les boutons de page
        int maxButtons = 5; // Nombre max de boutons de page à afficher
        int startPage, endPage;

        if (totalPages <= maxButtons) {
            startPage = 1;
            endPage = totalPages;
        } else {
            int halfButtons = maxButtons / 2;
            if (currentPage <= halfButtons + 1) {
                startPage = 1;
                endPage = maxButtons;
            } else if (currentPage >= totalPages - halfButtons) {
                startPage = totalPages - maxButtons + 1;
                endPage = totalPages;
            } else {
                startPage = currentPage - halfButtons;
                endPage = currentPage + halfButtons;
            }
        }

        // Ajouter les boutons de page
        for (int i = startPage; i <= endPage; i++) {
            final int pageNum = i;
            Button pageBtn = new Button(String.valueOf(i));

            if (i == currentPage) {
                pageBtn.getStyleClass().add("btn-page-active");
            } else {
                pageBtn.getStyleClass().add("btn-page");
            }

            pageBtn.setOnAction(e -> goToPage(pageNum));
            paginationContainer.getChildren().add(pageBtn);
        }

        // Points de suspension si nécessaire
        if (endPage < totalPages) {
            Label dots = new Label("...");
            dots.setStyle("-fx-text-fill: #6c757d; -fx-padding: 5 10;");
            paginationContainer.getChildren().add(dots);

            Button lastPageBtn = new Button(String.valueOf(totalPages));
            lastPageBtn.getStyleClass().add("btn-page");
            lastPageBtn.setOnAction(e -> goToPage(totalPages));
            paginationContainer.getChildren().add(lastPageBtn);
        }

        // Bouton Suivant
        Button nextBtn = new Button("Suivant »");
        nextBtn.getStyleClass().add("btn-pagination");
        nextBtn.setDisable(currentPage == totalPages);
        nextBtn.setOnAction(e -> handleNextPage());
        paginationContainer.getChildren().add(nextBtn);
    }

    /**
     * Aller à une page spécifique
     */
    private void goToPage(int pageNum) {
        if (pageNum >= 1 && pageNum <= totalPages) {
            currentPage = pageNum;
            displayCurrentPage();
            updatePaginationUI();
        }
    }

    /**
     * Page précédente
     */
    @FXML
    private void handlePrevPage() {
        if (currentPage > 1) {
            currentPage--;
            displayCurrentPage();
            updatePaginationUI();
        }
    }

    /**
     * Page suivante
     */
    private void handleNextPage() {
        if (currentPage < totalPages) {
            currentPage++;
            displayCurrentPage();
            updatePaginationUI();
        }
    }
}