from flask import Flask, request, jsonify, send_from_directory
import sqlite3
import os
import json
import time
import threading
import datetime

# Create Flask apps for both servers
expertise_app = Flask("expertise_server")
dashboard_app = Flask("dashboard_server")

# Initialize database
def init_db():
    conn = sqlite3.connect('p2p_network.db')
    cursor = conn.cursor()
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS nodes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nodeId TEXT,
        key TEXT,
        expertise TEXT,
        model TEXT,
        last_announced TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(nodeId, key)
    )
    ''')
    conn.commit()
    conn.close()
    print("Database initialized")

init_db()

# Expertise endpoint on port 9999
@expertise_app.route('/expertise', methods=['POST'])
def receive_expertise():
    try:
        data = request.json
        
        if not data or 'nodeId' not in data or 'embeddings' not in data:
            return jsonify({"error": "Invalid request format"}), 400
        
        nodeId = data['nodeId']
        embeddings = data['embeddings']
        
        conn = sqlite3.connect('p2p_network.db')
        cursor = conn.cursor()
        
        for embedding in embeddings:
            key = embedding.get('key')
            expertise = embedding.get('expertise')
            model = embedding.get('model')
            
            # Upsert operation with timestamp update
            cursor.execute('''
            INSERT OR REPLACE INTO nodes (nodeId, key, expertise, model, last_announced)
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ''', (nodeId, key, expertise, model))
        
        conn.commit()
        conn.close()
        
        return jsonify({"success": True})
    
    except Exception as e:
        print(f"Error processing request: {e}")
        return jsonify({"error": "Internal server error"}), 500

# Function to clean up old entries (nodes not announced in the last 2 minutes)
def cleanup_old_entries():
    while True:
        try:
            conn = sqlite3.connect('p2p_network.db')
            cursor = conn.cursor()
            
            # Delete entries older than 2 minutes (120 seconds)
            cursor.execute('''
            DELETE FROM nodes 
            WHERE datetime(last_announced) < datetime('now', '-120 seconds')
            ''')
            
            conn.commit()
            if cursor.rowcount > 0:
                print(f"Cleaned up {cursor.rowcount} expired node entries")
            conn.close()
        except Exception as e:
            print(f"Error during cleanup: {e}")
        
        # Sleep for 30 seconds before checking again
        time.sleep(30)

# Start the cleanup thread
cleanup_thread = threading.Thread(target=cleanup_old_entries, daemon=True)
cleanup_thread.start()

# Dashboard endpoint for listing servers
@dashboard_app.route('/api/nodes', methods=['GET'])
def get_nodes():
    try:
        conn = sqlite3.connect('p2p_network.db')
        conn.row_factory = sqlite3.Row  # This enables column access by name
        cursor = conn.cursor()
        
        cursor.execute('SELECT nodeId, key, expertise, model, last_announced FROM nodes')
        rows = cursor.fetchall()
        
        # Convert to list of dictionaries
        nodes = [dict(row) for row in rows]
        
        conn.close()
        return jsonify(nodes)
    
    except Exception as e:
        print(f"Error fetching nodes: {e}")
        return jsonify({"error": "Database error"}), 500

# Serve dashboard HTML
@dashboard_app.route('/', methods=['GET'])
def dashboard():
    return send_from_directory('public', 'index.html')

# Serve static files
@dashboard_app.route('/<path:path>')
def static_files(path):
    return send_from_directory('public', path)

# Create public directory if it doesn't exist
os.makedirs('public', exist_ok=True)

# Create a simple HTML dashboard
with open('public/index.html', 'w') as f:
    f.write('''
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P2P-RAG Network Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h1 {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <h1>P2P Network Dashboard</h1>
    
    <div id="nodes-container">
        <h2>Network Nodes</h2>
        <table id="nodes-table">
            <thead>
                <tr>
                    <th>Node ID</th>
                    <th>Key</th>
                    <th>Expertise</th>
                    <th>Model</th>
                    <th>Last Announced</th>
                </tr>
            </thead>
            <tbody id="nodes-body">
                <!-- Data will be populated here -->
            </tbody>
        </table>
    </div>

    <script>
        // Function to fetch and display nodes
        function fetchNodes() {
            fetch('/api/nodes')
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.getElementById('nodes-body');
                    tableBody.innerHTML = '';
                    
                    if (data.length === 0) {
                        const row = document.createElement('tr');
                        row.innerHTML = '<td colspan="5">No nodes available</td>';
                        tableBody.appendChild(row);
                    } else {
                        data.forEach(node => {
                            const row = document.createElement('tr');
                            // Format the timestamp
                            const lastAnnounced = new Date(node.last_announced);
                            const timeAgo = Math.round((Date.now() - lastAnnounced) / 1000) - 3601;
                            const timeAgoStr = timeAgo < 60 ? 
                                `${timeAgo}s ago` : 
                                `${Math.round(timeAgo/60)}m ${timeAgo%60}s ago`;
                            
                            row.innerHTML = `
                                <td>${node.nodeId}</td>
                                <td>${node.key}</td>
                                <td>${node.expertise}</td>
                                <td>${node.model}</td>
                                <td>${timeAgoStr}</td>
                            `;
                            tableBody.appendChild(row);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching nodes:', error);
                    const tableBody = document.getElementById('nodes-body');
                    tableBody.innerHTML = '<tr><td colspan="5">Error loading nodes</td></tr>';
                });
        }

        // Fetch nodes when page loads
        document.addEventListener('DOMContentLoaded', fetchNodes);
        
        // Refresh nodes every seconds
        setInterval(fetchNodes, 1000);
    </script>
</body>
</html>
    ''')

# Define server functions outside the main block
def run_expertise_server():
    expertise_app.run(host='0.0.0.0', port=9999)

def run_dashboard_server():
    dashboard_app.run(host='0.0.0.0', port=3000)

if __name__ == '__main__':
    # Start expertise server in a separate process
    import multiprocessing
    
    expertise_process = multiprocessing.Process(target=run_expertise_server)
    dashboard_process = multiprocessing.Process(target=run_dashboard_server)
    
    expertise_process.start()
    dashboard_process.start()
    
    print("Expertise server running on port 9999")
    print("Dashboard server running on port 3000")
    
    try:
        expertise_process.join()
        dashboard_process.join()
    except KeyboardInterrupt:
        print("Shutting down servers...")
        expertise_process.terminate()
        dashboard_process.terminate()
        expertise_process.join()
        dashboard_process.join()