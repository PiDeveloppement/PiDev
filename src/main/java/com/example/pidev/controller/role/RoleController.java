package com.example.pidev.controller.role;

import com.example.pidev.MainController;
import com.example.pidev.model.role.Role;
import com.example.pidev.service.role.RoleService;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.collections.transformation.FilteredList;
import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;

import java.net.URL;
import java.sql.SQLException;
import java.util.ResourceBundle;

public class RoleController implements Initializable {

    /* ================= FIELDS ================= */
    @FXML private Label totalRolesLabel;
    @FXML private VBox totalRolesCard;

    @FXML private TextField searchField;

    @FXML private TableView<Role> roleTable;
    @FXML private TableColumn<Role, String> roleNameColumn;
    @FXML private TableColumn<Role, Void> actionsColumn;



    @FXML private Label statsLabel;
    @FXML private Label paginationLabel;


    // Boutons de pagination
    @FXML private Button prevPageBtn;
    @FXML private Button nextPageBtn;
    @FXML private Button page1Btn;
    @FXML private Button page2Btn;
    @FXML private Button page3Btn;
    @FXML private Button lastPageBtn;

    private RoleService roleService;
    private ObservableList<Role> rolesList;
    private FilteredList<Role> filteredData;
    private MainController mainController;

    private int currentPage = 1;
    private final int rowsPerPage = 5;
    private int totalPages = 1;

    /* ================= INITIALIZE ================= */

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        try {
            System.out.println("✅ RoleController initialisé");

            roleService = new RoleService();
            rolesList = FXCollections.observableArrayList();

            initializeTableColumns();

            // Configuration de la table - SANS SCROLL INTERNE
            roleTable.setFixedCellSize(45);
            roleTable.setPrefHeight(5 * 45 + 28);
            roleTable.setMinHeight(5 * 45 + 28);
            roleTable.setMaxHeight(5 * 45 + 28);

            // Cacher les barres de scroll de la table
            roleTable.setStyle("-fx-bar-policy: never;");
            roleTable.setPlaceholder(new Label("Aucun rôle à afficher"));

            // Initialiser filteredData
            filteredData = new FilteredList<>(rolesList, p -> true);
            roleTable.setItems(filteredData);

            // Load roles
            loadRoles();

            setupActionsColumn();
            setupSearch();
            setupPaginationControls();



        } catch (SQLException e) {
            showAlert("Erreur", e.getMessage());
            e.printStackTrace();
        } catch (Exception e) {
            showAlert("Erreur", "Erreur lors de l'initialisation: " + e.getMessage());
            e.printStackTrace();
        }
    }

    private void initializeTableColumns() {
        roleNameColumn.setCellValueFactory(new PropertyValueFactory<>("roleName"));
    }

    private void loadRoles() {
        rolesList.setAll(roleService.getAllRoles());
        filteredData = new FilteredList<>(rolesList, p -> true);
        updateStats();
        updateTotalRolesCount();
        currentPage = 1;
        updateTableWithPagination();
    }

    private void updateStats() {
        if (statsLabel != null) {
            statsLabel.setText(rolesList.size() + " rôles");
        }
    }

    /* ================= PAGINATION ================= */

    private void setupPaginationControls() {
        if (page1Btn != null) {
            page1Btn.setOnAction(e -> goToPage(1));
            page2Btn.setOnAction(e -> goToPage(2));
            page3Btn.setOnAction(e -> goToPage(3));
            prevPageBtn.setOnAction(e -> goToPage(currentPage - 1));
            nextPageBtn.setOnAction(e -> goToPage(currentPage + 1));
            if (lastPageBtn != null) {
                lastPageBtn.setOnAction(e -> goToPage(totalPages));
            }
        }
    }

    private void updateTableWithPagination() {
        if (filteredData == null || filteredData.isEmpty()) {
            roleTable.setItems(FXCollections.observableArrayList());
            if (paginationLabel != null) {
                paginationLabel.setText("Page 0 sur 0");
            }
            if (statsLabel != null) {
                statsLabel.setText("0 rôles");
            }
            return;
        }

        int totalItems = filteredData.size();
        totalPages = (int) Math.ceil((double) totalItems / rowsPerPage);
        if (totalPages == 0) totalPages = 1;

        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        int fromIndex = (currentPage - 1) * rowsPerPage;
        int toIndex = Math.min(fromIndex + rowsPerPage, totalItems);

        if (fromIndex < totalItems) {
            ObservableList<Role> pageData = FXCollections.observableArrayList(
                    filteredData.subList(fromIndex, toIndex)
            );
            roleTable.setItems(pageData);
            roleTable.refresh();
        }

        if (paginationLabel != null) {
            paginationLabel.setText("Page " + currentPage + " sur " + totalPages);
        }

        if (statsLabel != null) {
            statsLabel.setText(totalItems + " rôles");
        }

        updatePaginationButtons();
    }

    private void goToPage(int page) {
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        updateTableWithPagination();
    }

    private void updatePaginationButtons() {
        if (page1Btn == null) return;

        page1Btn.setStyle(getPageButtonStyle(1));
        page2Btn.setStyle(getPageButtonStyle(2));
        page3Btn.setStyle(getPageButtonStyle(3));

        if (prevPageBtn != null) prevPageBtn.setDisable(currentPage == 1);
        if (nextPageBtn != null) nextPageBtn.setDisable(currentPage == totalPages);
        if (lastPageBtn != null) lastPageBtn.setDisable(currentPage == totalPages);

        if (totalPages >= 1) {
            page1Btn.setText("1");
            page1Btn.setVisible(true);
        }
        if (totalPages >= 2) {
            page2Btn.setText("2");
            page2Btn.setVisible(true);
        } else {
            page2Btn.setVisible(false);
        }
        if (totalPages >= 3) {
            page3Btn.setText("3");
            page3Btn.setVisible(true);
        } else {
            page3Btn.setVisible(false);
        }
        if (lastPageBtn != null) {
            lastPageBtn.setText(String.valueOf(totalPages));
            lastPageBtn.setVisible(totalPages > 3);
        }
    }

    private String getPageButtonStyle(int page) {
        if (page == currentPage) {
            return "-fx-background-color: #2196F3; -fx-text-fill: white; -fx-font-weight: bold; -fx-min-width: 36; -fx-min-height: 36; -fx-background-radius: 8; -fx-cursor: hand;";
        } else {
            return "-fx-background-color: white; -fx-text-fill: #475569; -fx-min-width: 36; -fx-min-height: 36; -fx-background-radius: 8; -fx-border-color: #e2e8f0; -fx-border-width: 1; -fx-cursor: hand;";
        }
    }

    /* ================= AJOUT / MODIFICATION ================= */

    @FXML
    private void addRoleButtonOnAction(ActionEvent e) {
        if (mainController != null) {
            mainController.loadAddRolePage(); // Mode ajout
        } else {
            showAlert("Erreur", "Impossible d'ouvrir la page d'ajout");
        }
    }

    /* ================= DELETE ================= */

    private void deleteRole(Role role) {
        boolean confirmed = showConfirmation(
                "Confirmer",
                "Supprimer le rôle " + role.getRoleName() + " ?"
        );

        if (!confirmed) return;

        if (roleService.deleteRole(role.getId_Role())) {
            showAlert("Succès", "Rôle supprimé");
            loadRoles();
        }
    }

    /* ================= ACTION COLUMN ================= */

    private void setupActionsColumn() {
        actionsColumn.setCellFactory(param -> new TableCell<Role, Void>() {
            private final Button editBtn = new Button();
            private final Button deleteBtn = new Button();
            private final HBox container = new HBox(10, editBtn, deleteBtn);

            {
                // Bouton Modifier
                Label editIcon = new Label("\uD83D\uDCDD"); // 📝
                editIcon.setStyle("-fx-font-size: 16px; -fx-text-fill: white;");
                editBtn.setGraphic(editIcon);
                editBtn.setStyle(
                        "-fx-background-color: #3b82f6;" +
                                "-fx-background-radius: 8;" +
                                "-fx-text-fill: white;" +
                                "-fx-font-size: 13px;" +
                                "-fx-font-weight: bold;" +
                                "-fx-padding: 8 12 8 12;" +
                                "-fx-cursor: hand;"
                );

                Tooltip editTooltip = new Tooltip("Modifier");
                editTooltip.setStyle("-fx-background-color: #1f2937; -fx-text-fill: white; -fx-font-size: 12px;");
                editBtn.setTooltip(editTooltip);

                editBtn.setOnAction(e -> {
                    Role role = getTableView().getItems().get(getIndex());
                    if (mainController != null) {
                        mainController.loadEditRolePage(role); // Mode modification
                    } else {
                        showAlert("Erreur", "Impossible d'ouvrir la page de modification");
                    }
                });

                // Bouton Supprimer
                Label deleteIcon = new Label("\u2702"); // ✂️
                deleteIcon.setStyle("-fx-font-size: 16px; -fx-text-fill: white;");
                deleteBtn.setGraphic(deleteIcon);
                deleteBtn.setStyle(
                        "-fx-background-color: #ef4444;" +
                                "-fx-background-radius: 8;" +
                                "-fx-text-fill: white;" +
                                "-fx-font-size: 13px;" +
                                "-fx-font-weight: bold;" +
                                "-fx-padding: 8 12 8 12;" +
                                "-fx-cursor: hand;"
                );

                Tooltip deleteTooltip = new Tooltip("Supprimer");
                deleteTooltip.setStyle("-fx-background-color: #1f2937; -fx-text-fill: white; -fx-font-size: 12px;");
                deleteBtn.setTooltip(deleteTooltip);

                deleteBtn.setOnAction(e -> {
                    Role role = getTableView().getItems().get(getIndex());
                    deleteRole(role);
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : container);
            }
        });
    }

    /* ================= SEARCH ================= */

    private void setupSearch() {
        if (searchField == null) return;

        searchField.textProperty().addListener((obs, oldValue, newValue) -> {
            applyFilters();
        });
    }

    private void applyFilters() {
        if (filteredData == null) return;

        String keyword = searchField != null ? searchField.getText().toLowerCase().trim() : "";

        filteredData.setPredicate(role -> {
            if (keyword.isEmpty()) return true;
            return role.getRoleName().toLowerCase().contains(keyword);
        });

        currentPage = 1;
        updateTableWithPagination();
    }

    /* ================= UTILS ================= */

    private void showAlert(String title, String msg) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION, msg);
        alert.setTitle(title);
        alert.showAndWait();
    }

    private boolean showConfirmation(String title, String msg) {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION, msg);
        alert.setTitle(title);
        return alert.showAndWait().orElse(ButtonType.CANCEL) == ButtonType.OK;
    }

    private void updateTotalRolesCount() {
        try {
            int totalCount = roleService.getTotalRolesCount();
            if (totalRolesLabel != null) {
                totalRolesLabel.setText(String.valueOf(totalCount));
                Tooltip tooltip = new Tooltip("Nombre total de rôles");
                tooltip.setStyle("-fx-background-color: #1f2937; -fx-text-fill: white; -fx-font-size: 12px;");
                totalRolesLabel.setTooltip(tooltip);
            }
        } catch (Exception e) {
            System.err.println("Erreur compteur rôles: " + e.getMessage());
            if (totalRolesLabel != null) {
                totalRolesLabel.setText("0");
            }
        }
    }

    public void setMainController(MainController mainController) {
        this.mainController = mainController;
    }
}