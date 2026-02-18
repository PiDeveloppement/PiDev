package com.example.pidev.service.event;

import com.example.pidev.model.event.EventTicket;
import com.example.pidev.utils.MyDatabase;

import java.sql.*;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;

/**
 * Service pour gérer les opérations CRUD sur les tickets
 * @author Ons Abdesslem
 */
public class EventTicketService {

    private final Connection connection;

    // ==================== CONSTRUCTEUR ====================

    public EventTicketService() {
        this.connection = MyDatabase.getInstance().getConnection();
    }

    // ==================== CREATE ====================

    /**
     * Créer un nouveau ticket
     * @param eventId ID de l'événement
     * @param userId ID de l'utilisateur
     * @return Le ticket créé ou null si erreur
     */
    public EventTicket createTicket(int eventId, int userId) {
        String ticketCode = EventTicket.generateTicketCode(eventId, userId);

        String sql = "INSERT INTO event_ticket (ticket_code, event_id, user_id) VALUES (?, ?, ?)";

        try (PreparedStatement pstmt = connection.prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {

            pstmt.setString(1, ticketCode);
            pstmt.setInt(2, eventId);
            pstmt.setInt(3, userId);

            int rowsAffected = pstmt.executeUpdate();

            if (rowsAffected > 0) {
                // Récupérer l'ID généré
                try (ResultSet rs = pstmt.getGeneratedKeys()) {
                    if (rs.next()) {
                        EventTicket ticket = new EventTicket(ticketCode, eventId, userId);
                        ticket.setId(rs.getInt(1));
                        ticket.setCreatedAt(LocalDateTime.now());
                        System.out.println("✅ Ticket créé: " + ticketCode);
                        return ticket;
                    }
                }
            }

        } catch (SQLException e) {
            System.err.println("❌ Erreur création ticket: " + e.getMessage());
            e.printStackTrace();
        }

        return null;
    }

    // ==================== READ ====================

    /**
     * Récupérer tous les tickets
     */
    public List<EventTicket> getAllTickets() {
        List<EventTicket> tickets = new ArrayList<>();
        String sql = "SELECT * FROM event_ticket ORDER BY created_at DESC";

        try (Statement stmt = connection.createStatement();
             ResultSet rs = stmt.executeQuery(sql)) {

            while (rs.next()) {
                tickets.add(extractTicketFromResultSet(rs));
            }

            System.out.println("✅ " + tickets.size() + " tickets récupérés");

        } catch (SQLException e) {
            System.err.println("❌ Erreur récupération tickets: " + e.getMessage());
            e.printStackTrace();
        }

        return tickets;
    }

    /**
     * Récupérer un ticket par son ID
     */
    public EventTicket getTicketById(int id) {
        String sql = "SELECT * FROM event_ticket WHERE id = ?";

        try (PreparedStatement pstmt = connection.prepareStatement(sql)) {

            pstmt.setInt(1, id);

            try (ResultSet rs = pstmt.executeQuery()) {
                if (rs.next()) {
                    return extractTicketFromResultSet(rs);
                }
            }

        } catch (SQLException e) {
            System.err.println("❌ Erreur récupération ticket ID=" + id + ": " + e.getMessage());
            e.printStackTrace();
        }

        return null;
    }

    /**
     * Récupérer les tickets d'un événement
     */
    public List<EventTicket> getTicketsByEvent(int eventId) {
        List<EventTicket> tickets = new ArrayList<>();
        String sql = "SELECT * FROM event_ticket WHERE event_id = ? ORDER BY created_at";

        try (PreparedStatement pstmt = connection.prepareStatement(sql)) {

            pstmt.setInt(1, eventId);

            try (ResultSet rs = pstmt.executeQuery()) {
                while (rs.next()) {
                    tickets.add(extractTicketFromResultSet(rs));
                }
            }

        } catch (SQLException e) {
            System.err.println("❌ Erreur récupération tickets event " + eventId + ": " + e.getMessage());
            e.printStackTrace();
        }

        return tickets;
    }

    /**
     * Récupérer les tickets d'un utilisateur
     */
    public List<EventTicket> getTicketsByUser(int userId) {
        List<EventTicket> tickets = new ArrayList<>();
        String sql = "SELECT * FROM event_ticket WHERE user_id = ? ORDER BY created_at DESC";

        try (PreparedStatement pstmt = connection.prepareStatement(sql)) {

            pstmt.setInt(1, userId);

            try (ResultSet rs = pstmt.executeQuery()) {
                while (rs.next()) {
                    tickets.add(extractTicketFromResultSet(rs));
                }
            }

        } catch (SQLException e) {
            System.err.println("❌ Erreur récupération tickets user " + userId + ": " + e.getMessage());
            e.printStackTrace();
        }

        return tickets;
    }

    /**
     * Récupérer un ticket par son code
     */
    public EventTicket getTicketByCode(String ticketCode) {
        String sql = "SELECT * FROM event_ticket WHERE ticket_code = ?";

        try (PreparedStatement pstmt = connection.prepareStatement(sql)) {

            pstmt.setString(1, ticketCode);

            try (ResultSet rs = pstmt.executeQuery()) {
                if (rs.next()) {
                    return extractTicketFromResultSet(rs);
                }
            }

        } catch (SQLException e) {
            System.err.println("❌ Erreur récupération ticket code " + ticketCode + ": " + e.getMessage());
            e.printStackTrace();
        }

        return null;
    }

    // ==================== UPDATE ====================

    /**
     * Marquer un ticket comme utilisé (check-in)
     */
    public boolean markTicketAsUsed(int ticketId) {
        String sql = "UPDATE event_ticket SET is_used = TRUE, used_at = NOW() WHERE id = ?";

        try (PreparedStatement pstmt = connection.prepareStatement(sql)) {

            pstmt.setInt(1, ticketId);

            int rowsAffected = pstmt.executeUpdate();

            if (rowsAffected > 0) {
                System.out.println("✅ Ticket ID=" + ticketId + " marqué comme utilisé");
                return true;
            }

        } catch (SQLException e) {
            System.err.println("❌ Erreur mise à jour ticket: " + e.getMessage());
            e.printStackTrace();
        }

        return false;
    }

    /**
     * Mettre à jour un ticket (admin)
     */
    public boolean updateTicket(EventTicket ticket) {
        String sql = "UPDATE event_ticket SET ticket_code = ?, event_id = ?, user_id = ?, is_used = ?, used_at = ? WHERE id = ?";

        try (PreparedStatement pstmt = connection.prepareStatement(sql)) {

            pstmt.setString(1, ticket.getTicketCode());
            pstmt.setInt(2, ticket.getEventId());
            pstmt.setInt(3, ticket.getUserId());
            pstmt.setBoolean(4, ticket.isUsed());
            pstmt.setTimestamp(5, ticket.getUsedAt() != null ? Timestamp.valueOf(ticket.getUsedAt()) : null);
            pstmt.setInt(6, ticket.getId());

            int rowsAffected = pstmt.executeUpdate();

            if (rowsAffected > 0) {
                System.out.println("✅ Ticket ID=" + ticket.getId() + " mis à jour");
                return true;
            }

        } catch (SQLException e) {
            System.err.println("❌ Erreur mise à jour ticket: " + e.getMessage());
            e.printStackTrace();
        }

        return false;
    }

    // ==================== DELETE ====================

    /**
     * Supprimer un ticket
     */
    public boolean deleteTicket(int id) {
        String sql = "DELETE FROM event_ticket WHERE id = ?";

        try (PreparedStatement pstmt = connection.prepareStatement(sql)) {

            pstmt.setInt(1, id);
            int rowsAffected = pstmt.executeUpdate();

            if (rowsAffected > 0) {
                System.out.println("✅ Ticket supprimé (ID=" + id + ")");
                return true;
            }

        } catch (SQLException e) {
            System.err.println("❌ Erreur suppression ticket: " + e.getMessage());
            e.printStackTrace();
        }

        return false;
    }

    // ==================== STATISTIQUES ====================

    /**
     * Compter le nombre de tickets pour un événement
     */
    public int countTicketsByEvent(int eventId) {
        String sql = "SELECT COUNT(*) as count FROM event_ticket WHERE event_id = ?";

        try (PreparedStatement pstmt = connection.prepareStatement(sql)) {

            pstmt.setInt(1, eventId);

            try (ResultSet rs = pstmt.executeQuery()) {
                if (rs.next()) {
                    return rs.getInt("count");
                }
            }

        } catch (SQLException e) {
            System.err.println("❌ Erreur comptage tickets: " + e.getMessage());
            e.printStackTrace();
        }

        return 0;
    }

    /**
     * Compter le nombre de tickets utilisés (présents) pour un événement
     */
    public int countUsedTicketsByEvent(int eventId) {
        String sql = "SELECT COUNT(*) as count FROM event_ticket WHERE event_id = ? AND is_used = TRUE";

        try (PreparedStatement pstmt = connection.prepareStatement(sql)) {

            pstmt.setInt(1, eventId);

            try (ResultSet rs = pstmt.executeQuery()) {
                if (rs.next()) {
                    return rs.getInt("count");
                }
            }

        } catch (SQLException e) {
            System.err.println("❌ Erreur comptage tickets utilisés: " + e.getMessage());
            e.printStackTrace();
        }

        return 0;
    }

    // ==================== MÉTHODES PRIVÉES ====================

    /**
     * Extraire un ticket depuis un ResultSet
     */
    private EventTicket extractTicketFromResultSet(ResultSet rs) throws SQLException {
        EventTicket ticket = new EventTicket();

        ticket.setId(rs.getInt("id"));
        ticket.setTicketCode(rs.getString("ticket_code"));
        ticket.setEventId(rs.getInt("event_id"));
        ticket.setUserId(rs.getInt("user_id"));
        ticket.setQrCode(rs.getString("qr_code"));
        ticket.setUsed(rs.getBoolean("is_used"));

        Timestamp usedTimestamp = rs.getTimestamp("used_at");
        if (usedTimestamp != null) {
            ticket.setUsedAt(usedTimestamp.toLocalDateTime());
        }

        Timestamp createdTimestamp = rs.getTimestamp("created_at");
        if (createdTimestamp != null) {
            ticket.setCreatedAt(createdTimestamp.toLocalDateTime());
        }

        return ticket;
    }
}