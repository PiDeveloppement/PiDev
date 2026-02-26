package com.melocode.pigestion.service;

import com.melocode.pigestion.model.Evenement;
import com.melocode.pigestion.model.Question;
import com.melocode.pigestion.utils.MyConnection;
import java.sql.*;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class QuestionService {
    private final Connection conn = MyConnection.getInstance().getCnx();

    /**
     * Charge la liste des événements pour les ComboBox.
     */
    public List<Evenement> chargerEvenements() throws SQLException {
        List<Evenement> list = new ArrayList<>();
        String req = "SELECT id, title FROM event";
        try (Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(req)) {
            while (rs.next()) {
                list.add(new Evenement(rs.getInt("id"), rs.getString("title")));
            }
        }
        return list;
    }

    /**
     * Affiche toutes les questions avec le titre de l'événement associé.
     */
    public List<Question> afficherTout() throws SQLException {
        List<Question> questions = new ArrayList<>();
        // Jointure SQL pour récupérer le titre de l'événement
        String req = "SELECT q.*, e.title as event_title " +
                "FROM questions q " +
                "JOIN event e ON q.id_event = e.id";

        try (Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(req)) {
            while (rs.next()) {
                Question q = mapResultSetToQuestion(rs);
                // On utilise l'alias 'event_title' défini dans la requête
                q.setNomEvent(rs.getString("event_title"));
                questions.add(q);
            }
        }
        return questions;
    }

    /**
     * Filtre les questions par événement.
     */
    public List<Question> afficherParEvenement(int idEvent) throws SQLException {
        List<Question> liste = new ArrayList<>();
        String req = "SELECT q.*, e.title FROM questions q " +
                "JOIN event e ON q.id_event = e.id " +
                "WHERE q.id_event = ?";

        try (PreparedStatement pst = conn.prepareStatement(req)) {
            pst.setInt(1, idEvent);
            try (ResultSet rs = pst.executeQuery()) {
                while (rs.next()) {
                    Question q = mapResultSetToQuestion(rs);
                    q.setNomEvent(rs.getString("title"));
                    liste.add(q);
                }
            }
        }
        return liste;
    }

    /**
     * Ajoute une nouvelle question.
     */
    public void ajouter(Question q) throws SQLException {
        // On ajoute id_user dans la requête
        String req = "INSERT INTO questions (id_event, texte_question, bonne_reponse, points, id_user) VALUES (?, ?, ?, ?, ?)";

        try (PreparedStatement pst = conn.prepareStatement(req)) {
            pst.setInt(1, q.getIdEvent());
            pst.setString(2, q.getTexte());
            pst.setString(3, q.getReponse());
            pst.setInt(4, q.getPoints());
            pst.setInt(5, 1); // <--- Ici, on envoie l'ID de l'utilisateur (ex: 1)

            pst.executeUpdate();
        }
    }

    /**
     * Modifie une question existante.
     */
    public void modifier(Question q) throws SQLException {
        String req = "UPDATE questions SET id_event=?, texte_question=?, bonne_reponse=?, points=? WHERE id_question=?";
        try (PreparedStatement pst = conn.prepareStatement(req)) {
            pst.setInt(1, q.getIdEvent());
            pst.setString(2, q.getTexte());
            pst.setString(3, q.getReponse());
            pst.setInt(4, q.getPoints());
            pst.setInt(5, q.getIdQuestion());
            pst.executeUpdate();
        }
    }

    /**
     * Supprime une question et ses feedbacks associés (intégrité référentielle).
     */
    public void supprimer(int id) throws SQLException {
        // Désactiver l'auto-commit pour une transaction sécurisée
        try {
            conn.setAutoCommit(false);

            // 1. Supprimer les feedbacks liés
            try (PreparedStatement pstF = conn.prepareStatement("DELETE FROM feedbacks WHERE id_question = ?")) {
                pstF.setInt(1, id);
                pstF.executeUpdate();
            }

            // 2. Supprimer la question
            try (PreparedStatement pstQ = conn.prepareStatement("DELETE FROM questions WHERE id_question = ?")) {
                pstQ.setInt(1, id);
                pstQ.executeUpdate();
            }

            conn.commit();
        } catch (SQLException e) {
            conn.rollback();
            throw e;
        } finally {
            conn.setAutoCommit(true);
        }
    }

    /**
     * Récupère les statistiques (Nombre de questions par titre d'événement).
     */
    public Map<String, Integer> obtenirStats() throws SQLException {
        Map<String, Integer> stats = new HashMap<>();
        String req = "SELECT e.title, COUNT(q.id_question) as total " +
                "FROM questions q " +
                "JOIN event e ON q.id_event = e.id " +
                "GROUP BY e.title";

        try (Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(req)) {
            while (rs.next()) {
                stats.put(rs.getString("title"), rs.getInt("total"));
            }
        }
        return stats;
    }

    /**
     * Méthode utilitaire pour éviter la répétition du mapping ResultSet -> Objet Question.
     */
    private Question mapResultSetToQuestion(ResultSet rs) throws SQLException {
        return new Question(
                rs.getInt("id_question"),
                rs.getInt("id_event"),
                rs.getString("texte_question"),
                rs.getString("bonne_reponse"),
                rs.getInt("points")
        );
    }
}