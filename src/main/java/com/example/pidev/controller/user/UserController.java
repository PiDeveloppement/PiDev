package com.example.pidev.controller.user;

import com.example.pidev.model.role.Role;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.service.role.RoleService;
import com.example.pidev.service.user.UserService;

import javafx.beans.binding.Bindings;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.collections.transformation.FilteredList;
import javafx.collections.transformation.SortedList;
import javafx.event.ActionEvent;
import javafx.fxml.*;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import javafx.stage.Stage;

import java.net.URL;
import java.sql.SQLException;
import java.util.ResourceBundle;

public class UserController implements Initializable {

    /* ================= FIELDS ================= */

    @FXML private TextField searchField,firstnameField, lastnameField, emailField, faculteField, passwordField;
    @FXML private ComboBox<String> roleComboBox;

    @FXML private TableView<UserModel> userTable;
    @FXML private TableColumn<UserModel,Integer> id_column;
    @FXML private TableColumn<UserModel,String> firstname_column, lastname_column,
            email_column, faculte_column, role_id_column, password_column;
    @FXML private TableColumn<UserModel,Void> actions_column;
    @FXML private ComboBox<String> faculteFilterCombo;
    @FXML private ComboBox<String> roleFilterCombo;


    private UserService userService;
    private RoleService roleService;
    private ObservableList<UserModel> usersList;
    private FilteredList<UserModel> filteredData;      // filtrée
    private SortedList<UserModel> sortedData;


    /* ================= INITIALIZE ================= */

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        try {
            userService = new UserService();
            roleService = new RoleService();
            usersList = FXCollections.observableArrayList();

            initializeTableColumns();

            // Setup filtered et sorted data
            filteredData = new FilteredList<>(usersList, b -> true);
            sortedData = new SortedList<>(filteredData);
            sortedData.comparatorProperty().bind(userTable.comparatorProperty());
            userTable.setItems(sortedData);

            // Charger les données
            loadUsers();

            // === CHARGER LES LISTES POUR LES FILTRES ===
            loadFaculteFilterList();
            loadRoleFilterList();

            // Configurer le ComboBox des rôles pour le formulaire
            roleComboBox.setItems(roleService.getAllRoleNames());

            setupSearch();
            setupActionsColumn();

            userTable.getSelectionModel().selectedItemProperty()
                    .addListener((obs,o,n) -> { if(n!=null) fillForm(n); });

            userTable.setFixedCellSize(40);
            userTable.prefHeightProperty().bind(
                    Bindings.size(userTable.getItems())
                            .multiply(userTable.getFixedCellSize())
                            .add(40)
            );

        } catch (SQLException e) {
            showAlert("Erreur", e.getMessage());
        }
    }
    private void initializeTableColumns() {

        id_column.setCellValueFactory(new PropertyValueFactory<>("id_User"));
        firstname_column.setCellValueFactory(new PropertyValueFactory<>("first_Name"));
        lastname_column.setCellValueFactory(new PropertyValueFactory<>("last_Name"));
        email_column.setCellValueFactory(new PropertyValueFactory<>("email"));
        faculte_column.setCellValueFactory(new PropertyValueFactory<>("faculte"));
        password_column.setCellValueFactory(new PropertyValueFactory<>("password"));

        role_id_column.setCellValueFactory(cell -> {
            Role r = cell.getValue().getRole();
            return new SimpleStringProperty(r != null ? r.getRoleName() : "");

        });

    }


    private void loadUsers() {
        usersList.setAll(userService.getAllUsers());
        loadFaculteFilterList();

    }


    /* ================= CREATE ================= */

    @FXML
    private void registerButtonOnAction(ActionEvent e) {
        createUser();
    }

    private void createUser() {

        if (!validateFields()) return;

        UserModel user = new UserModel(
                firstnameField.getText(),
                lastnameField.getText(),
                emailField.getText(),
                faculteField.getText(),
                passwordField.getText(),
                1
        );

        if (userService.registerUser(user)) {
            showAlert("Succès", "Utilisateur créé avec succès");
            loadUsers();
            loadFaculteFilterList();
            resetForm();
        }
    }


    /* ================= UPDATE ================= */

    @FXML

    private void modifyButtonOnAction(ActionEvent e) {
        try {
            UserModel selected = userTable.getSelectionModel().getSelectedItem();

            if (selected == null) {
                showAlert("Erreur", "Sélectionnez un utilisateur");
                return;
            }

            updateUser(selected);

        } catch (SQLException ex) {
            showAlert("Erreur BD", ex.getMessage());
        }
    }



    private void updateUser(UserModel userModel) throws SQLException {
        if (!validateFields()) return;

        userModel.setFirst_Name(firstnameField.getText());
        userModel.setLast_Name(lastnameField.getText());
        userModel.setEmail(emailField.getText());
        userModel.setFaculte(faculteField.getText());
        userModel.setPassword(passwordField.getText());

        // Get selected role name from ComboBox
        String roleName = roleComboBox.getValue();

        // VALIDATION: Check if a role is selected
        if (roleName == null || roleName.isEmpty()) {
            showAlert("Erreur", "Veuillez sélectionner un rôle");
            return;
        }

        // Get role ID by name
        int roleId = roleService.getRoleIdByName(roleName);

        // VALIDATION: Check if role ID is valid
        if (roleId <= 0) {
            showAlert("Erreur", "Rôle invalide ou inexistant");
            return;
        }

        userModel.setRole_Id(roleId);

        if (userService.updateUser(userModel)) {
            showAlert("Succès", "Utilisateur modifié");
            loadUsers();
            resetForm();
        }
    }




    /* ================= DELETE ================= */

    private void deleteUser(UserModel user) {

        boolean confirmed = showConfirmation(
                "Confirmer",
                "Supprimer " + user.getFirst_Name() + " ?"
        );

        if (!confirmed) return;

        if (userService.deleteUser(user.getId_User())) {
            showAlert("Succès", "Utilisateur supprimé");
            loadUsers();

        }
    }


    /* ================= ACTION COLUMN ================= */

    private void setupActionsColumn() {

        actions_column.setCellFactory(param -> new TableCell<UserModel, Void>() {


            private final Button deleteBtn = new Button("🗑");
            private final HBox container = new HBox(10,  deleteBtn);

            {



                // Bouton Supprimer
                deleteBtn.setStyle(
                        "-fx-background-color: #ef4444;" +
                                "-fx-text-fill: white;" +
                                "-fx-background-radius: 8;" +
                                "-fx-cursor: hand;"
                );

                deleteBtn.setOnAction(e -> {
                    UserModel user = getTableView().getItems().get(getIndex());
                    deleteUser(user);
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : container);
            }
        });
    }



    /* ================= FORM ================= */

    private void fillForm(UserModel u) {
        firstnameField.setText(u.getFirst_Name());
        lastnameField.setText(u.getLast_Name());
        emailField.setText(u.getEmail());
        faculteField.setText(u.getFaculte());
        passwordField.setText(u.getPassword());
        if (u.getRole() != null && u.getRole().getRoleName() != null) {
            roleComboBox.setValue(u.getRole().getRoleName());
        }
    }

    private void resetForm() {
        firstnameField.clear();
        lastnameField.clear();
        emailField.clear();
        faculteField.clear();
        passwordField.clear();
        roleComboBox.getSelectionModel().clearSelection();
    }

    private boolean validateFields() {
        if (firstnameField.getText().isEmpty()
                || lastnameField.getText().isEmpty()
                || emailField.getText().isEmpty()) {
            showAlert("Champs manquants", "Veuillez remplir tous les champs");
            return false;
        }
        return true;
    }


    /* ================= UTILS ================= */

    private void showAlert(String title, String msg) {
        new Alert(Alert.AlertType.INFORMATION, msg).showAndWait();
    }

    private boolean showConfirmation(String title, String msg) {
        return new Alert(Alert.AlertType.CONFIRMATION, msg)
                .showAndWait().orElse(ButtonType.CANCEL) == ButtonType.OK;
    }


    /* ================= NAVIGATION ================= */

    @FXML
    private void goToLogin(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/auth/login.fxml")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception ignored) {}
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
        } catch (Exception ignored) {}
    }
    @FXML
    private void goToGestionUser(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/user/user.fxml")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception ignored) {}
    }
    @FXML
    private void goToDashboard(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/dashboard/dashboard.fxml")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception ignored) {}
    }
    @FXML
    private void goToProfil(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/user/profil.fxml")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception ignored) {}
    }
    private void setupSearch() {
        // Remove this line: filteredData.setPredicate(user -> true);
        // Remove this line: userTable.setItems(sortedData);

        searchField.textProperty().addListener((obs, oldValue, newValue) -> {
            String keyword = newValue.toLowerCase().trim();

            filteredData.setPredicate(user -> {
                if (keyword.isEmpty()) return true;

                boolean matchesFirstName = user.getFirst_Name().toLowerCase().contains(keyword);
                boolean matchesLastName = user.getLast_Name().toLowerCase().contains(keyword);
                boolean matchesEmail = user.getEmail().toLowerCase().contains(keyword);
                boolean matchesFaculte = user.getFaculte().toLowerCase().contains(keyword);

                // Also search by role name if available
                boolean matchesRole = user.getRole() != null &&
                        user.getRole().getRoleName().toLowerCase().contains(keyword);

                return matchesFirstName || matchesLastName || matchesEmail || matchesFaculte || matchesRole;
            });
        });
    }
    /**
     * Charge la liste unique des facultés depuis la base de données
     */
    private void loadFaculteFilterList() {
        try {
            ObservableList<String> faculteList = FXCollections.observableArrayList();
            faculteList.addAll(userService.getAllFacultes());
            faculteFilterCombo.setItems(faculteList);
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    /**
     * Charge la liste unique des rôles depuis la base de données
     */
    private void loadRoleFilterList() {
        try {
            ObservableList<String> roleList = FXCollections.observableArrayList();
            roleList.addAll(roleService.getAllRoleNames());
            roleFilterCombo.setItems(roleList);
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    @FXML
    private void filterByFaculte(ActionEvent event) {
        String selectedFaculte = faculteFilterCombo.getValue();
        String currentSearch = searchField.getText().toLowerCase().trim();
        String selectedRole = roleFilterCombo.getValue(); // Récupère le filtre rôle actuel

        filteredData.setPredicate(user -> {
            // 1. Filtre par faculté
            boolean matchesFaculte = (selectedFaculte == null || selectedFaculte.isEmpty()) ||
                    user.getFaculte().equalsIgnoreCase(selectedFaculte);

            // 2. Filtre par rôle (indépendant)
            boolean matchesRole = (selectedRole == null || selectedRole.isEmpty()) ||
                    (user.getRole() != null &&
                            user.getRole().getRoleName().equalsIgnoreCase(selectedRole));

            // 3. Filtre par recherche
            boolean matchesSearch = currentSearch.isEmpty() ||
                    user.getFirst_Name().toLowerCase().contains(currentSearch) ||
                    user.getLast_Name().toLowerCase().contains(currentSearch) ||
                    user.getEmail().toLowerCase().contains(currentSearch) ||
                    user.getFaculte().toLowerCase().contains(currentSearch) ||
                    (user.getRole() != null && user.getRole().getRoleName().toLowerCase().contains(currentSearch));

            return matchesFaculte && matchesRole && matchesSearch;
        });
    }

    @FXML
    private void filterByRole(ActionEvent event) {
        String selectedRole = roleFilterCombo.getValue();
        String currentSearch = searchField.getText().toLowerCase().trim();
        String selectedFaculte = faculteFilterCombo.getValue(); // Récupère le filtre faculté actuel

        filteredData.setPredicate(user -> {
            // 1. Filtre par rôle
            boolean matchesRole = (selectedRole == null || selectedRole.isEmpty()) ||
                    (user.getRole() != null &&
                            user.getRole().getRoleName().equalsIgnoreCase(selectedRole));

            // 2. Filtre par faculté (indépendant)
            boolean matchesFaculte = (selectedFaculte == null || selectedFaculte.isEmpty()) ||
                    user.getFaculte().equalsIgnoreCase(selectedFaculte);

            // 3. Filtre par recherche
            boolean matchesSearch = currentSearch.isEmpty() ||
                    user.getFirst_Name().toLowerCase().contains(currentSearch) ||
                    user.getLast_Name().toLowerCase().contains(currentSearch) ||
                    user.getEmail().toLowerCase().contains(currentSearch) ||
                    user.getFaculte().toLowerCase().contains(currentSearch) ||
                    (user.getRole() != null && user.getRole().getRoleName().toLowerCase().contains(currentSearch));

            return matchesRole && matchesFaculte && matchesSearch;
        });
    }

    @FXML
    private void resetFilters(ActionEvent event) {
        // Réinitialiser les sélections
        faculteFilterCombo.getSelectionModel().clearSelection();
        roleFilterCombo.getSelectionModel().clearSelection();

        // Réinitialiser la recherche
        searchField.clear();

        // Afficher tous les utilisateurs
        filteredData.setPredicate(user -> true);
    }


}
