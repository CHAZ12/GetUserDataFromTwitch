from flask import Flask, request, jsonify
import time
import requests
import psycopg2
from config import config

app = Flask(__name__)

def connect():
    try:
        params = config()
        connection = psycopg2.connect(**params)
        cursor = connection.cursor()
        return connection, cursor
    except (Exception, psycopg2.DatabaseError) as error:
        print(f'Database connection failed: {error}')
        return None, None

def html_escape(text):
    return text.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;').replace('"', '&quot;').replace("'", '&#39;')

def convert_seconds(seconds):
    m, s = divmod(seconds, 60)
    h, m = divmod(m, 60)
    d, h = divmod(h, 24)
    return d, h, m, s

@app.route('/', methods=['GET', 'POST'])
def watchtime():
    connection, cursor = connect()
    if connection is None or cursor is None:
        print({'error': 'Database connection failed'},500)
        return jsonify({'error': 'Database connection failed'}), 500
    
    try:
        if request.method == 'GET':
            channel = request.args.get('channel')
            action = request.args.get('action')
        elif request.method == 'POST':
            data = request.get_json()
            channel = data.get('channel')
            action = data.get('action')
        else:
            print({'error': 'Method not allowed'}, 405)
            return jsonify({'error': 'Method not allowed'}), 405

        if not channel:
            print({'error': 'Empty channel'}, 400)
            return jsonify({'error': 'Empty channel'}), 400
        
        if not action:
            print({'error': 'Empty action (get/update)'},400)
            return jsonify({'error': 'Empty action (get/update)'}), 400

        channel = html_escape(str(channel).lower())

        cursor.execute('SELECT * FROM watchtime WHERE username = %s', (channel,))
        row = cursor.fetchone()
        now = int(time.time())
        
        if action != 'get':
            try:
                if not row:
                    print(channel, " :channel is not found in database")
                    cursor.execute('INSERT INTO watchtime (username, watch_seconds, last_updated) VALUES (%s, %s, %s)', (channel.lower(), 0, now))
                    connection.commit()    
                else:
                    print(channel, " :channel is found in database")
                    new_watch_seconds = row[1] + (now - row[2])
                    cursor.execute('UPDATE watchtime SET watch_seconds = %s WHERE username = %s', (new_watch_seconds, channel))
                    connection.commit()
                    
            except (Exception,psycopg2.DatabaseError) as e:
                print(f"An error occured: {e}")
            
            if action == 'update':
                print('UPDATE?')
                cursor.execute('SELECT * FROM watchtime WHERE username = %s', (channel,))
                channel_row = cursor.fetchone()
                print(channel_row[2])
                print(now)
                print(now - int(channel_row[2]))
                if now - int(channel_row[2]) > 30:  # 10 minutes has passed
                    queries = [
                        ('mods', '{user(login: "' + channel + '") { channel { chatters { moderators { login } } } } }'),
                        ('viewers', '{user(login: "' + channel + '") { channel { chatters { viewers { login } } } } }'),
                        ('vips', '{user(login: "' + channel + '") { channel { chatters { vips { login } } } } }'),
                        ('viewer_count', '{user(login: "' + channel + '") { stream { viewersCount } } }')
                    ]
                    headers = {'Client-Id': 'kimne78kx3ncx6brgo4mv6wki5h1ko'}

                    results = {}
                    for key, query in queries:
                        response = requests.post("https://gql.twitch.tv/gql", json={'query': query}, headers=headers)
                        if response.status_code != 200:
                            print({'error': f'HTTP status code: {response.status_code}', 'response': response.text})
                            return jsonify({'error': f'HTTP status code: {response.status_code}', 'response': response.text}), 500
                        results[key] = response.json()

                    mods = results.get('mods', {}).get('data', {}).get('user', {}).get('channel', {}).get('chatters', {}).get('moderators', [])
                    vips = results.get('vips', {}).get('data', {}).get('user', {}).get('channel', {}).get('chatters', {}).get('vips', [])
                    viewers = results.get('viewers', {}).get('data', {}).get('user', {}).get('channel', {}).get('chatters', {}).get('viewers', [])
                    stream = results.get('viewer_count', {}).get('data', {}).get('user', {}).get('stream')
                    
                    if not stream:
                        print('Not Online')
                        return jsonify({'message': 'Not Online'}), 200
                    
                    print("GOT ALL USERS")
                    chatters = mods + vips + viewers
                    for viewer in chatters:
                        viewer_login = viewer['login']
                        cursor.execute('SELECT * FROM watchtime WHERE username = %s', (str(viewer_login).lower(),))
                        viewer_row = cursor.fetchone()
                        if viewer_row:
                            print("Got old viewer")
                            new_watch_seconds = viewer_row[1] + (int(time.time()) - viewer_row[2])
                            cursor.execute('UPDATE watchtime SET watch_seconds = %s, last_updated = %s WHERE username = %s', (new_watch_seconds, int(time.time()), viewer_login))
                            connection.commit()
                        else:
                            print("insert new viewer")
                            cursor.execute('INSERT INTO watchtime (username, watch_seconds, last_updated) VALUES (%s, %s, %s)', (viewer_login, 0, int(time.time())))
                            connection.commit()
                            
                    print("Getting new watch seconds")
                    print('Update Complete')
                    return jsonify({'message': 'Update Complete'}), 200
                else:
                    new_watch_seconds = channel[1] + (int(time.time()) - channel_row[2])
                    cursor.execute('UPDATE watchtime SET watch_seconds = %s, last_updated = %s WHERE username = %s', (new_watch_seconds, int(time.time()), channel)) # update streamer info
                    connection.commit()

        elif action == 'get':
            username = request.args.get('user')
            print(username)
            if not username:
                return jsonify({'error': 'Empty username'}), 400
            username = html_escape(username).lower()
            cursor.execute('SELECT * FROM watchtime WHERE username = %s', (username,))
            row = cursor.fetchone()
            print(row)
            if row:
                watch_seconds = row[1] + (int(time.time()) - row[2])
                d, h, m, s = convert_seconds(watch_seconds)
                time_str = ', '.join(f"{value} {name}" for value, name in zip([d, h, m, s], ["days", "hours", "minutes", "seconds"]) if value)
                return jsonify({'message': f'{username} watched the stream for {time_str}!'}), 200
            else:
                return jsonify({'message': f'Invalid username "{username}": moderator, too new or nonexistent'}), 200
        else:
            return jsonify({'error': 'Invalid action'}), 400

    except Exception as e:
        return jsonify({'error': str(e)}), 500

    finally:
        cursor.close()
        connection.close()

if __name__ == '__main__':
    app.run(debug=True)
