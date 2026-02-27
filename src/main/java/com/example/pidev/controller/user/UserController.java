package com.example.pidev.controller.user;

import com.example.pidev.MainController;
import com.example.pidev.model.role.Role;
import com.example.pidev.model.user.PasswordResetToken;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.service.role.RoleService;
import com.example.pidev.service.user.EmailService;
import com.example.pidev.service.user.PasswordResetService;
import com.example.pidev.service.user.UserService;

import javafx.beans.binding.Bindings;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.collections.transformation.FilteredList;
import javafx.event.ActionEvent;
import javafx.fxml.*;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import javafx.stage.Stage;
import javafx.scene.control.Tooltip;
import javafx.scene.control.Alert;
import javafx.scene.control.ButtonType;
import javafx.scene.control.TableCell;

import java.net.URL;
import java.sql.SQLException;
import java.util.ResourceBundle;

public class UserController implements Initializable {

    /* ================= FIELDS ================= */

    @FXML private TextField searchField;

    private MainController mainController;
    @FXML private TableView<UserModel> userTable;
    @FXML private TableColumn<UserModel, String> firstname_column;
    @FXML private TableColumn<UserModel, String> lastname_column;
    @FXML private TableColumn<UserModel, String> email_column;
    @FXML private TableColumn<UserModel, String> faculte_column;
    @FXML private TableColumn<UserModel, String> password_column;
    @FXML private TableColumn<UserModel, String> role_column;
    @FXML private TableColumn<UserModel, Void> actions_column;

    @FXML private ComboBox<String> faculteFilterCombo;
    @FXML private ComboBox<String> roleFilterCombo;

    private UserService userService;
    private RoleService roleService;
    private ObservableList<UserModel> usersList;
    private FilteredList<UserModel> filteredData;

    @FXML private Label totalParticipantsLabel;
    @FXML private VBox totalParticipantsCard;
    @FXML private Button prevPageBtn;
    @FXML private Button nextPageBtn;
    @FXML private Button page1Btn;
    @FXML private Button page2Btn;
    @FXML private Button page3Btn;
    @FXML private Button lastPageBtn;
    @FXML private Label paginationLabel;
    @FXML private Label statsLabel;

    private int currentPage = 1;
    private final int rowsPerPage = 5;
    private int totalPages = 1;

    /* ================= INITIALIZE ================= */


    @Override
    public void initialize(URL url, ResourceBundle rb) {
        try {
            userService = new UserService();
            roleService = new RoleService();
            usersList = FXCollections.observableArrayList();

            initializeTableColumns();

            // Configuration de la table - SANS SCROLL INTERNE
            userTable.setFixedCellSize(45);
            userTable.setPrefHeight(300);
            userTable.setMinHeight(300);
            userTable.setMaxHeight(300);

            // CACHER LES BARRES DE SCROLL DE LA TABLE
            userTable.setStyle("-fx-bar-policy: never;");

            userTable.setPlaceholder(new Label("Aucune donnée à afficher"));

            // Charger les données
            loadUsers();

            // Configurer les filtres
            loadFaculteFilterList();
            loadRoleFilterList();

            setupSearch();
            setupActionsColumn();
            setupPaginationControls();

        } catch (SQLException e) {
            showAlert("Erreur", e.getMessage());
            e.printStackTrace();
        }
    }

    private void initializeTableColumns() {
        firstname_column.setCellValueFactory(new PropertyValueFactory<>("first_Name"));
        lastname_column.setCellValueFactory(new PropertyValueFactory<>("last_Name"));
        email_column.setCellValueFactory(new PropertyValueFactory<>("email"));
        faculte_column.setCellValueFactory(new PropertyValueFactory<>("faculte"));
        password_column.setCellValueFactory(new PropertyValueFactory<>("password"));

        role_column.setCellValueFactory(cell -> {
            Role r = cell.getValue().getRole();
            return new SimpleStringProperty(r != null ? r.getRoleName() : "");
        });
    }

    private void loadUsers() {
        try {
            // Chargez les données réelles
            usersList.setAll(userService.getAllUsers());

            // SI AUCUNE DONNÉE, AJOUTEZ DES DONNÉES FACTICES POUR TEST
            if (usersList.isEmpty()) {
                System.out.println("⚠️ AUCUNE DONNÉE - Création de données factices");
                // Créez quelques utilisateurs factices pour tester l'affichage
                for (int i = 1; i <= 10; i++) {
                    UserModel testUser = new UserModel(i, "Test" + i, "User" + i, "test" + i + "@test.com", "Faculté", "pass", 1);
                    usersList.add(testUser);
                }
            }

            System.out.println("=== CHARGEMENT DES UTILISATEURS ===");
            System.out.println("Nombre d'utilisateurs chargés: " + usersList.size());

            filteredData = new FilteredList<>(usersList, p -> true);
            updateTotalParticipantsCount();
            currentPage = 1;
            updateTableWithPagination();

        } catch (Exception e) {
            e.printStackTrace();
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
            lastPageBtn.setOnAction(e -> goToPage(totalPages));
        }
    }

    private void updateTableWithPagination() {
        if (filteredData == null || filteredData.isEmpty()) {
            userTable.setItems(FXCollections.observableArrayList());
            if (paginationLabel != null) {
                paginationLabel.setText("Page 0 sur 0");
            }
            if (statsLabel != null) {
                statsLabel.setText("0 utilisateurs");
            }
            return;
        }

        int totalItems = filteredData.size();
        totalPages = (int) Math.ceil((double) totalItems / rowsPerPage);
        if (totalPages == 0) totalPages = 1;

        // Ajuster la page courante
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }

        int fromIndex = (currentPage - 1) * rowsPerPage;
        int toIndex = Math.min(fromIndex + rowsPerPage, totalItems);

        if (fromIndex < totalItems) {
            ObservableList<UserModel> pageData = FXCollections.observableArrayList(
                    filteredData.subList(fromIndex, toIndex)
            );
            userTable.setItems(pageData);
            System.out.println("Affichage de " + pageData.size() + " utilisateurs");

            // FORCER LE RAFRAÎCHISSEMENT
            userTable.refresh();
        }

        if (paginationLabel != null) {
            paginationLabel.setText("Page " + currentPage + " sur " + totalPages);
        }

        if (statsLabel != null) {
            statsLabel.setText(totalItems + " utilisateurs");
        }

        updatePaginationButtons();
    }

    private void updatePaginationButtons() {
        if (page1Btn == null) return;

        // Style des boutons de page
        page1Btn.setStyle(getPageButtonStyle(1));
        page2Btn.setStyle(getPageButtonStyle(2));
        page3Btn.setStyle(getPageButtonStyle(3));

        // Activer/désactiver les boutons
        if (prevPageBtn != null) prevPageBtn.setDisable(currentPage == 1);
        if (nextPageBtn != null) nextPageBtn.setDisable(currentPage == totalPages);
        if (lastPageBtn != null) lastPageBtn.setDisable(currentPage == totalPages);

        // Visibilité des boutons
        page1Btn.setVisible(totalPages >= 1);
        page2Btn.setVisible(totalPages >= 2);
        page3Btn.setVisible(totalPages >= 3);
    }

    private String getPageButtonStyle(int page) {
        if (page == currentPage) {
            return "-fx-background-color: #2196F3; -fx-text-fill: white; -fx-font-weight: bold; -fx-min-width: 36; -fx-min-height: 36; -fx-background-radius: 8; -fx-cursor: hand;";
        } else {
            return "-fx-background-color: white; -fx-text-fill: #475569; -fx-min-width: 36; -fx-min-height: 36; -fx-background-radius: 8; -fx-border-color: #e2e8f0; -fx-border-width: 1; -fx-cursor: hand;";
        }
    }

    /* ================= FILTRES ================= */

    private void setupFilters() {
        if (faculteFilterCombo != null) {
            faculteFilterCombo.setOnAction(e -> applyFilters());
        }
        if (roleFilterCombo != null) {
            roleFilterCombo.setOnAction(e -> applyFilters());
        }
    }

    private void setupSearch() {
        if (searchField != null) {
            searchField.textProperty().addListener((obs, oldValue, newValue) -> {
                applyFilters();
            });
        }
    }

    private void loadFaculteFilterList() {
        try {
            if (faculteFilterCombo != null) {
                ObservableList<String> faculteList = FXCollections.observableArrayList();
                faculteList.addAll(userService.getAllFacultes());
                faculteFilterCombo.setItems(faculteList);
            }
        } catch (Exception e) {
            System.err.println("Erreur lors du chargement des facultés: " + e.getMessage());
        }
    }

    private void loadRoleFilterList() {
        try {
            if (roleFilterCombo != null) {
                ObservableList<String> roleList = FXCollections.observableArrayList();
                roleList.addAll(roleService.getAllRoleNames());
                roleFilterCombo.setItems(roleList);
            }
        } catch (Exception e) {
            System.err.println("Erreur lors du chargement des rôles: " + e.getMessage());
        }
    }

    @FXML
    private void filterByFaculte(ActionEvent event) {
        applyFilters();
    }

    @FXML
    private void filterByRole(ActionEvent event) {
        applyFilters();
    }

    @FXML
    private void resetFilters(ActionEvent event) {
        if (faculteFilterCombo != null) faculteFilterCombo.getSelectionModel().clearSelection();
        if (roleFilterCombo != null) roleFilterCombo.getSelectionModel().clearSelection();
        if (searchField != null) searchField.clear();
        applyFilters();
    }

    private void applyFilters() {
        if (filteredData == null || usersList == null) return;

        String selectedFaculte = faculteFilterCombo != null ? faculteFilterCombo.getValue() : null;
        String selectedRole = roleFilterCombo != null ? roleFilterCombo.getValue() : null;
        String keyword = searchField != null ? searchField.getText().toLowerCase().trim() : "";

        filteredData.setPredicate(user -> {
            // Filtre par faculté
            boolean matchesFaculte = (selectedFaculte == null || selectedFaculte.isEmpty()) ||
                    (user.getFaculte() != null && user.getFaculte().equalsIgnoreCase(selectedFaculte));

            // Filtre par rôle
            boolean matchesRole = (selectedRole == null || selectedRole.isEmpty()) ||
                    (user.getRole() != null && user.getRole().getRoleName() != null &&
                            user.getRole().getRoleName().equalsIgnoreCase(selectedRole));

            // Filtre par recherche
            boolean matchesSearch = keyword.isEmpty() ||
                    (user.getFirst_Name() != null && user.getFirst_Name().toLowerCase().contains(keyword)) ||
                    (user.getLast_Name() != null && user.getLast_Name().toLowerCase().contains(keyword)) ||
                    (user.getEmail() != null && user.getEmail().toLowerCase().contains(keyword)) ||
                    (user.getFaculte() != null && user.getFaculte().toLowerCase().contains(keyword)) ||
                    (user.getRole() != null && user.getRole().getRoleName() != null &&
                            user.getRole().getRoleName().toLowerCase().contains(keyword));

            return matchesFaculte && matchesRole && matchesSearch;
        });

        // Retour à la première page
        currentPage = 1;
        updateTableWithPagination();
    }

    /* ================= ACTIONS ================= */

    private void setupActionsColumn() {
        if (actions_column == null) return;

        actions_column.setCellFactory(param -> new TableCell<UserModel, Void>() {
            private final Button editBtn = new Button();
            private final Button deleteBtn = new Button();
            private final HBox container = new HBox(10, editBtn, deleteBtn);

            {
                // Bouton Modifier
                Label editIcon = new Label("\uD83D\uDCDD");
                editIcon.setStyle("-fx-font-size: 16px; -fx-text-fill: white;");
                editBtn.setGraphic(editIcon);
                editBtn.setStyle("-fx-background-color: #3b82f6; -fx-background-radius: 8; -fx-text-fill: white; -fx-font-size: 13px; -fx-font-weight: bold; -fx-padding: 8 12 8 12; -fx-cursor: hand; -fx-border: none;");

                Tooltip editTooltip = new Tooltip("Modifier");
                editTooltip.setStyle("-fx-background-color: #1f2937; -fx-text-fill: white; -fx-font-size: 12px;");
                editBtn.setTooltip(editTooltip);

                editBtn.setOnAction(e -> {
                    UserModel user = getTableView().getItems().get(getIndex());
                    openEditPage(user);
                });

                // Bouton Supprimer
                Label deleteIcon = new Label("\u2702");
                deleteIcon.setStyle("-fx-font-size: 16px; -fx-text-fill: white;");
                deleteBtn.setGraphic(deleteIcon);
                deleteBtn.setStyle("-fx-background-color: #ef4444; -fx-background-radius: 8; -fx-text-fill: white; -fx-font-size: 13px; -fx-font-weight: bold; -fx-padding: 8 12 8 12; -fx-cursor: hand; -fx-border: none;");

                Tooltip deleteTooltip = new Tooltip("Supprimer");
                deleteTooltip.setStyle("-fx-background-color: #1f2937; -fx-text-fill: white; -fx-font-size: 12px;");
                deleteBtn.setTooltip(deleteTooltip);

                deleteBtn.setOnAction(e -> {
                    UserModel user = getTableView().getItems().get(getIndex());
                    confirmAndDelete(user);
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                if (empty) {
                    setGraphic(null);
                } else {
                    container.setAlignment(javafx.geometry.Pos.CENTER);
                    setGraphic(container);
                }
            }
        });
    }

    private void deleteUser(UserModel user) {
        boolean confirmed = showConfirmation("Confirmer", "Supprimer " + user.getFirst_Name() + " ?");

        if (!confirmed) return;

        if (userService.deleteUser(user.getId_User())) {
            showAlert("Succès", "Utilisateur supprimé");
            loadUsers();
            goToPage(1);
        }
    }

    private void openEditPage(UserModel user) {
        if (mainController != null) {
            mainController.loadEditUserPage(user);
        } else {
            showAlert("Erreur", "Impossible d'ouvrir la page de modification");
        }
    }

    private void confirmAndDelete(UserModel user) {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
        alert.setTitle("Confirmation de suppression");
        alert.setHeaderText("Supprimer l'utilisateur");
        alert.setContentText("Êtes-vous sûr de vouloir supprimer " + user.getFirst_Name() + " ?");

        DialogPane dialogPane = alert.getDialogPane();
        dialogPane.setStyle("-fx-background-color: white; -fx-font-size: 14px;");

        Button okButton = (Button) dialogPane.lookupButton(ButtonType.OK);
        okButton.setStyle("-fx-background-color: #ef4444; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 8 16; -fx-background-radius: 6;");

        Button cancelButton = (Button) dialogPane.lookupButton(ButtonType.CANCEL);
        cancelButton.setStyle("-fx-background-color: #e5e7eb; -fx-text-fill: #374151; -fx-padding: 8 16; -fx-background-radius: 6;");

        alert.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                deleteUser(user);
            }
        });
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

    @FXML
    private void goToRole(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/role/role.fxml")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private void updateTotalParticipantsCount() {
        try {
            int totalCount = userService.getTotalParticipantsCount();
            if (totalParticipantsLabel != null) {
                totalParticipantsLabel.setText(String.valueOf(totalCount));
            }
        } catch (Exception e) {
            System.err.println("Erreur compteur: " + e.getMessage());
            if (totalParticipantsLabel != null) {
                totalParticipantsLabel.setText("0");
            }
        }
    }
    private void goToPage(int page) {
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        updateTableWithPagination();
    }
    public void setMainController(MainController mainController) {
        this.mainController = mainController;
    }
    @FXML
    private void handleForgotPassword() {
        // Créer une boîte de dialogue pour entrer l'email
        TextInputDialog dialog = new TextInputDialog();
        dialog.setTitle("Mot de passe oublié");
        dialog.setHeaderText("Réinitialisation de mot de passe");
        dialog.setContentText("Entrez votre adresse email :");

        dialog.showAndWait().ifPresent(email -> {
            try {
                // Vérifier si l'email existe
                UserModel user = userService.getUserByEmail(email);
                if (user == null) {
                    showAlert("Erreur", "Aucun compte trouvé avec cet email");
                    return;
                }

                // Créer un token
                PasswordResetToken token = new PasswordResetToken(user.getId_User());

                // Sauvegarder le token
                PasswordResetService tokenService = new PasswordResetService();
                tokenService.createToken(token);

                // Envoyer l'email avec le lien
                EmailService.sendResetPasswordEmail(email, user.getFirst_Name(), token.getToken());

                showAlert("Succès", "Un lien de réinitialisation a été envoyé à votre adresse email.\nVérifiez votre boîte de réception (ou MailDev sur http://localhost:1080)");

            } catch (Exception e) {
                e.printStackTrace();
                showAlert("Erreur", "Erreur lors de l'envoi de l'email: " + e.getMessage());
            }
        });
    }
}